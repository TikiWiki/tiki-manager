<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\InstanceConfigure;
use TikiManager\Hooks\InstanceRestoreHook;

class RestoreInstanceCommand extends TikiManagerCommand
{
    use InstanceConfigure;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:restore')
            ->setDescription('Restore a blank installation')
            ->setHelp('This command allows you to restore a blank installation')
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check files checksum after operation has been performed.'
            )
            ->addOption(
                'skip-config-check',
                null,
                InputOption::VALUE_NONE,
                'Skip system_configuration_file check.'
            )
            ->addOption(
                'allow-common-parent-levels',
                null,
                InputOption::VALUE_REQUIRED,
                'Allow files and folders to be restored if they share the n-th parent use 0 (default) for the instance root folder and N (>=1) for allowing parent folders. Use -1 to skip this check',
                "0"
            )
            ->addOption(
                'validate',
                null,
                InputOption::VALUE_NONE,
                'Attempt to validate the instance by checking its URL.'
            )
            ->addOption(
                'copy-errors',
                null,
                InputOption::VALUE_OPTIONAL,
                'Handle rsync errors: use "stop" to halt on errors or "ignore" to proceed despite errors'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('no-tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        $restorableInstances = CommandHelper::getInstances('restore');
        $restorableInstancesInfo = CommandHelper::getInstancesInfo($restorableInstances);

        $checksumCheck = $input->getOption('check');
        $skipSystemConfigurationCheck = $input->getOption('skip-config-check') !== null;
        $allowCommonParents = (int)$input->getOption('allow-common-parent-levels');

        if (isset($instancesInfo) && isset($restorableInstancesInfo)) {
            $this->io->note('It is only possible to restore a backup on a blank install.');
            $this->io->warning('If you are restoring to the same server, this can lead to ' .
                         'data corruption as both the original and restored Tiki are using the ' .
                         'same folder for storage.');

            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $selectedInstances = $this->io->ask(
                'Which instance(s) do you want to restore to?',
                null,
                function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                }
            );

            $hookName = $this->getCommandHook();
            $InstanceRestoreHook = new InstanceRestoreHook($hookName->getHookName(), $this->logger);

            /** @var Instance $instance */
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Instance to restore to: ' . $instance->name . '</>');

                $this->io->newLine();
                CommandHelper::renderInstancesTable($output, $restorableInstancesInfo);

                $selectedRestorableInstances = $this->io->ask(
                    'Which instance do you want to restore from?',
                    null,
                    function ($answer) use ($restorableInstances) {
                        return CommandHelper::validateInstanceSelection($answer, $restorableInstances, CommandHelper::INSTANCE_SELECTION_SINGLE);
                    }
                );
                $restorableInstance = reset($selectedRestorableInstances);

                if ($restorableInstance->isInstanceProtected()) {
                    $output->writeln(sprintf('<error>Operation aborted: The source instance %s for restoration is protected using the \'sys_db_protected\' tag.</error>', $restorableInstance->name));
                    continue;
                }

                $files = $restorableInstance->getArchives();
                foreach ($files as $key => $path) {
                    $output->writeln('[' . $key . '] ' . basename($path));
                }

                $selectedArchive = $this->io->ask('Which backup do you want to restore?');
                $selection = getEntries($files, $selectedArchive);

                if (!$file = reset($selection)) {
                    $output->writeln('<comment>Skip: No archive file selected.</comment>');
                    continue;
                }

                $instance->app = $restorableInstance->app; // Required to setup database connection
                $instance->copy_errors = $input->getOption('copy-errors') ?: 'ask';

                $this->setupDatabase($instance);
                $instance->database()->setupConnection();

                $errors = $instance->restore(
                    $restorableInstance,
                    $file,
                    false,
                    $checksumCheck,
                    false,
                    false,
                    false,
                    [],
                    $skipSystemConfigurationCheck,
                    $allowCommonParents
                );

                if (isset($errors)) {
                    $InstanceRestoreHook->registerFailHookVars([
                        'error_message' => 'Failed to restore instance: ' . $instance->name,
                        'error_code' => 'FAIL_OPERATION_RESTORE_INSTANCE',
                        'instance' => $instance
                    ]);
                    $restorableInstance->updateState('failure', $this->getName(), 'restore function failure');
                    return 1;
                }

                $restorableInstance->updateState('success', $this->getName(), 'Instance restored');

                $this->io->success('It is now time to test your site: ' . $instance->name);
                $this->io->note([
                    'If there are issues, connect with make access to troubleshoot directly on the server.',
                    'You\'ll need to login to this restored instance and update the file paths with the new values.'
                ]);

                $hookName->registerPostHookVars(['instance' => $instance]);

                if ($input->getOption('validate')) {
                    CommandHelper::validateInstances([$instance], $InstanceRestoreHook);
                }
            }
        } else {
            $output->writeln('<comment>No instances available to restore to/from.</comment>');
        }

        return 0;
    }
}
