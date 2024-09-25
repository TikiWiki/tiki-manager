<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\InstanceConfigure;

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
            /** @var Instance $instance */
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Instance to restore to: ' . $instance->name . '</>');

                $this->io->newLine();
                CommandHelper::renderInstancesTable($output, $restorableInstancesInfo);

                $selectedRestorableInstances = $this->io->ask(
                    'Which instance do you want to restore from?',
                    null,
                    function ($answer) use ($restorableInstances) {
                        return CommandHelper::validateInstanceSelection($answer, $restorableInstances);
                    }
                );
                $restorableInstance = reset($selectedRestorableInstances);

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
            }
        } else {
            $output->writeln('<comment>No instances available to restore to/from.</comment>');
        }

        return 0;
    }
}
