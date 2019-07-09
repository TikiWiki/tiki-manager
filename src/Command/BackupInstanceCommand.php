<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Helpers\Archive;

class BackupInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:backup')
            ->setDescription('Backup instance')
            ->setHelp('This command allows you to backup instances')
            ->addOption(
                'instances',
                'ins',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Backup instances IDs (comma separated)'
            )
            ->addOption(
                'exclude',
                'e',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Exclude the backup of specific instance IDs'
            )
            ->addOption(
                'email',
                null,
                InputOption::VALUE_REQUIRED,
                'Email addresses to notify for backup failures.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $helper = $this->getHelper('question');

            $arguments = $input->getOption('instances');
            if (!empty($arguments)) {
                if ($arguments[0] == 'all') {
                    $this->checkForExcludedInstances($instances);
                    $selectedInstances = $instances;
                } else {
                    $instancesIds = explode(',', $arguments[0]);

                    $selectedInstances = [];
                    foreach ($instancesIds as $index) {
                        if (array_key_exists($index, $instances)) {
                            $selectedInstances[] = $instances[$index];
                        }
                    }
                }
            } else {
                $io->newLine();
                $output->writeln('<comment>NOTE: Backups are only available on Local and SSH instances.</comment>');

                $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

                $io->newLine();
                $output->writeln('<comment>In case you want to backup more than one instance, please use a comma (,) between the values</comment>');

                $question = CommandHelper::getQuestion('Which instance(s) do you want to backup', null, '?');
                $question->setValidator(function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                });

                $selectedInstances = $helper->ask($input, $output, $question);
            }

            $logs = [];
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Performing backup for ' . $instance->name . '</>');
                $log = [];
                $log[] = sprintf('## %s (id: %s)' . PHP_EOL, $instance->name, $instance->id);
                try {
                    $backupFile = $instance->backup();
                    if (!empty($backupFile)) {
                        $io->success('Backup created with success.');
                        $io->note('Backup file: ' . $backupFile);
                    } else {
                        $log[] = 'Failed to backup instance.';
                    }
                    Archive::performArchiveCleanup($instance->id, $instance->name);
                } catch (\Exception $e) {
                    $log[] = $e->getMessage() . PHP_EOL;
                    $log[] = $e->getTraceAsString() . PHP_EOL;
                }

                if (count($log) > 1) {
                    $logs = array_merge($logs, $log);
                }
            }

            $emails = $input->getOption('email');
            $emails = array_filter(explode(',', $emails), function ($email) {
                return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
            });

            if (!empty($logs) && !empty($emails)) {
                $logs = implode(PHP_EOL, $logs);
                try {
                    CommandHelper::sendMailNotification(
                        $emails,
                        '[Tiki-Manager] ' . $this->getName() . ' report failures',
                        $logs
                    );
                } catch (\RuntimeException $e) {
                    debug($e->getMessage());
                    $io->error($e->getMessage());
                }
            }
        } else {
            $output->writeln('<comment>No instances available to backup.</comment>');
        }

        return 0;
    }

    /**
     * Function used to check for instances to exclude via option "--exclude="
     * @param $instances
     */
    private function checkForExcludedInstances(&$instances)
    {
        $excluded_option = CommandHelper::getCliOption('exclude');
        if (empty($excluded_option)) {
            return;
        }

        $instances_to_exclude = explode(',', CommandHelper::getCliOption('exclude'));
        foreach ($instances as $key => $instance) {
            if (in_array($instance->id, $instances_to_exclude)) {
                unset($instances[$key]);
            }
        }
    }
}
