<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;

class RestoreInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:restore')
            ->setDescription('Restore a blank installation')
            ->setHelp('This command allows you to restore a blank installation')
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check files checksum after operation has been performed.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances('no-tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        $restorableInstances = CommandHelper::getInstances('restore');
        $restorableInstancesInfo = CommandHelper::getInstancesInfo($restorableInstances);

        $checksumCheck = $input->getOption('check');

        if (isset($instancesInfo) && isset($restorableInstancesInfo)) {
            $io->note('It is only possible to restore a backup on a blank install.');
            $io->warning('If you are restoring to the same server, this can lead to ' .
                         'data corruption as both the original and restored Tiki are using the ' .
                         'same folder for storage.');

            $io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $selectedInstances = $io->ask(
                'Which instance(s) do you want to restore to?',
                null,
                function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                }
            );

            /** @var Instance $instance */
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Instance to restore to: ' . $instance->name . '</>');

                $io->newLine();
                CommandHelper::renderInstancesTable($output, $restorableInstancesInfo);

                $selectedRestorableInstances = $io->ask(
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

                $selectedArchive = $io->ask('Which backup do you want to restore?');
                $selection = getEntries($files, $selectedArchive);

                if (!$file = reset($selection)) {
                    $output->writeln('<comment>Skip: No archive file selected.</comment>');
                    continue;
                }

                $instance->app = $restorableInstance->app; // Required to setup database connection
                $databaseConfig = CommandHelper::setupDatabaseConnection($instance, $input, $output);
                $instance->setDatabaseConfig($databaseConfig);

                $instance->restore($restorableInstance->app, $file, false, $checksumCheck);

                $output->writeln('<fg=cyan>It is now time to test your site: ' . $instance->name . '</>');
                $output->writeln('<fg=cyan>If there are issues, connect with make access to troubleshoot directly on the server.</>');
                $output->writeln('<fg=cyan>You\'ll need to login to this restored instance and update the file paths with the new values.</>');
            }
        } else {
            $output->writeln('<comment>No instances available to restore to/from.</comment>');
        }

        return 0;
    }
}
