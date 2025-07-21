<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\SendEmail;
use TikiManager\Config\Environment;

class BackupInstanceCommand extends TikiManagerCommand
{
    use SendEmail;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:backup')
            ->setDescription('Backup instance')
            ->setHelp('This command allows you to backup instances')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'Use "all" or a specific list of instances IDs or names (comma separated)'
            )
            ->addOption(
                'exclude',
                'x',
                InputOption::VALUE_REQUIRED,
                'Used with --instances=all, a list of instance IDs to exclude from backup'
            )
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Email addresses to notify for backup failures  (comma separated)'
            )
            ->addOption(
                'max-backups',
                'mb',
                InputOption::VALUE_REQUIRED,
                'Max number of backups to keep by instance'
            )->addOption(
                'partial',
                null,
                InputOption::VALUE_NONE,
                'Enable backups using VCS code base'
            )->addOption(
                'include-index-backup',
                null,
                InputOption::VALUE_NONE,
                'Include the index table in the backup'
            )
            ->addOption(
                'copy-errors',
                null,
                InputOption::VALUE_OPTIONAL,
                'Handle rsync errors: use "stop" to halt on errors or "ignore" to proceed despite errors'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->io->note('Backups are only available on Local and SSH instances.');

        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (isset($instancesInfo) && empty($input->getOption('instances'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $this->io->newLine();

            $instancesOption = $this->io->ask('Which instance(s) do you want to backup', 'all', function ($answer) use ($instances) {
                if ($answer == 'all') {
                    return $answer;
                }
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                return implode(',', CommandHelper::getInstanceIds($selectedInstances));
            });

            $input->setOption('instances', $instancesOption);
        }

        if (isset($instancesInfo) && $input->getOption('instances') == 'all' && empty($input->getOption('exclude'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $this->io->newLine();
            $this->io->writeln('<comment>In case you want to ignore more than one instance, please use a comma (,) between the values</comment>');

            $answer = $this->io->ask('Which instance IDs (or names) should be ignored?', null, function ($answer) use ($instances) {
                $excludeInstance = '';
                if (!empty($answer)) {
                    $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                    $excludeInstance = implode(',', CommandHelper::getInstanceIds($selectedInstances));
                }
                return $excludeInstance;
            });

            $input->setOption('exclude', $answer);
        }

        $email = $input->getOption('email');

        if (!$email) {
            $email = $this->io->ask('Email address to contact', null, function ($value) {
                if (empty(trim($value))) {
                    return null;
                }
                $emails = explode(',', $value);
                array_filter($emails, function ($email) {
                    return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
                });
                return implode(',', $emails);
            });
            $input->setOption('email', $email);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maxBackups = $input->getOption('max-backups') ?: Environment::get('DEFAULT_MAX_BACKUPS', 0);
        $isIncludeIndex = $input->getOption('include-index-backup') ? true : false;
        if (isset($maxBackups) && filter_var($maxBackups, FILTER_VALIDATE_INT) === false) {
            $this->io->error('Max number of backups to keep by instance is not a number');
            return 0;
        }

        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $this->io->writeln('<comment>No instances available to backup.</comment>');
            return 0;
        }

        $instancesOption = $input->getOption('instances');
        $selectedInstances = CommandHelper::validateInstanceSelection($instancesOption, $instances);

        if ($instancesOption === 'all' && !empty($input->getOption('exclude'))) {
            // Exclude option is only available with --instance=all (according the the option description).
            $instancesToExclude = CommandHelper::getInstanceIds(
                CommandHelper::validateInstanceSelection(
                    $input->getOption('exclude'),
                    $instances,
                    // We must not fail if there are deprecated instances in exclude parameter!
                    CommandHelper::INSTANCE_SELECTION_ALLOW_EMPTY | CommandHelper::INSTANCE_SELECTION_IGNORE_INVALID
                )
            );

            foreach ($selectedInstances as $key => $instance) {
                if (in_array($instance->id, $instancesToExclude)) {
                    unset($selectedInstances[$key]);
                }
            }
        }

        if (empty($selectedInstances)) {
            throw new \RuntimeException('No instances defined for backup');
        }

        $logs = [];

        $isFull = !$input->getOption('partial') ?? (Environment::get('BACKUP_TYPE', 'full') != 'partial');

        $hook = $this->getCommandHook();
        $bisectInstances = [];
        foreach ($selectedInstances as $instance) {
            $instance->copy_errors = $input->getOption('copy-errors') ?: 'ask';
            $isBisectSession = $instance->getOnGoingBisectSession();
            if ($isBisectSession) {
                $bisectInstances[] = $instance->id;
                continue;
            }
            $output->writeln('<fg=cyan>Performing backup for ' . $instance->name . '</>');
            $log = [];
            $log[] = sprintf('## %s (id: %s)' . PHP_EOL, $instance->name, $instance->id);
            try {
                $backupFile = $instance->backup(false, $isFull, false, $isIncludeIndex);
                if (!empty($backupFile)) {
                    $hook->registerPostHookVars(['instance' => $instance, 'backup_file' => $backupFile]);
                    $this->io->success('Backup created with success.');
                    $this->io->note('Backup file: ' . $backupFile);
                } else {
                    $log[] = 'Failed to backup instance.';
                }
                $instance->reduceBackups($maxBackups);
            } catch (\Exception $e) {
                $this->io->error($e->getMessage());
                $log[] = $e->getMessage() . PHP_EOL;
                $log[] = $e->getTraceAsString() . PHP_EOL;
            }

            if (count($log) > 1) {
                $logs = array_merge($logs, $log);
            }
        }

        if (count($bisectInstances)) {
            $backupSkippedErrMsg = "Backup is skipped for instances [%s] because bisect session is ongoing for these instance.";
            $backupSkippedErrMsg = sprintf($backupSkippedErrMsg, implode(',', $bisectInstances));
            $logs[] = $backupSkippedErrMsg;
            $this->io->warning($backupSkippedErrMsg);
        }

        $emails = $input->getOption('email') ?? '';
        $emails = array_filter(explode(',', $emails), function ($email) {
            return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        });

        if (!empty($logs) && !empty($emails)) {
            $logs = implode(PHP_EOL, $logs);
            try {
                $this->sendEmail(
                    $emails,
                    '[Tiki-Manager] ' . $this->getName() . ' report failures',
                    $logs
                );
            } catch (\RuntimeException $e) {
                debug($e->getMessage());
                $this->io->warning('Could not send an e-mail report: ' . $e->getMessage());
            }
        }

        if (count($bisectInstances)) {
            return 1;
        }

        return 0;
    }
}
