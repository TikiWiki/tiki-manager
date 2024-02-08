<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\App;
use TikiManager\Config\Environment;

class SetupBackupManagerCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('manager:setup-backups')
            ->setDescription('Set-up a cronjob to perform instance backups')
            ->setHelp('This command allows you to set-up a cron job on the Tiki Manager master to perform the backup of multiple instances automatically every day.')
            ->setAliases(['backups:setup'])
            ->addOption(
                'time',
                null,
                InputOption::VALUE_REQUIRED,
                'The time update should run'
            )
            ->addOption(
                'exclude',
                'x',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be excluded, separated by comma (,)'
            )
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Email address to report backup failures (multiple emails must be separated by comma (,)).'
            )
            ->addOption(
                'max-backups',
                'mb',
                InputOption::VALUE_REQUIRED,
                'Max number of backups to keep by instance'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('time'))) {
            $answer = $this->io->ask('What time should it run at?', '00:00', function ($answer) {
                return CommandHelper::validateTimeInput($answer);
            });

            $input->setOption('time', implode(':', $answer));
        }

        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (isset($instancesInfo) && empty($input->getOption('exclude'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $this->io->newLine();
            $this->io->writeln('<comment>In case you want to ignore more than one instance, please use a comma (,) between the values</comment>');

            $answer = $this->io->ask('Which instance IDs should be ignored?', null, function ($answer) use ($instances) {
                $excludeInstance = '';

                if (! empty($answer)) {
                    $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                    $excludeInstance = implode(',', CommandHelper::getInstanceIds($selectedInstances));
                }

                return $excludeInstance;
            });

            $input->setOption('exclude', $answer);
        }

        $email = $input->getOption('email');

        try {
            CommandHelper::validateEmailInput($email);
        } catch (\RuntimeException $e) {
            $this->io->error($e->getMessage());
            $email = null;
        }

        if (empty($email)) {
            $email = $this->io->ask('[Optional] Email address to contact in case of failures (use , to separate multiple emails)', null, function ($answer) {
                if (!empty($answer)) {
                    return  CommandHelper::validateEmailInput($answer);
                }
            });
            $input->setOption('email', $email);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $time = $input->getOption('time');
        list($hours, $minutes) = CommandHelper::validateTimeInput($time);
        $arguments = '--instances=all --no-interaction';
        $excludedInstances = $input->getOption('exclude');

        if (! empty($excludedInstances)) {
            $arguments .= ' --exclude=' . $excludedInstances;
        }

        $email = $input->getOption('email');
        $email = CommandHelper::validateEmailInput($email);

        if (!empty($email)) {
            $arguments .= ' --email=' . $email;
        }

        $maxBackups = $input->getOption('max-backups') ?: Environment::get('DEFAULT_MAX_BACKUPS', 0);
        if (isset($maxBackups) && filter_var($maxBackups, FILTER_VALIDATE_INT) === false) {
            $this->io->error('Max number of backups to keep by instance is not a number');
            return 1;
        }
        if ($maxBackups > 0) {
            $arguments .= ' --max-backups=' . $maxBackups;
        }

        $backupInstanceCommand = new BackupInstanceCommand();
        $managerPath = realpath(dirname(__FILE__) . '/../..');
        $entry = sprintf(
            "%d %d * * * cd %s && %s -d memory_limit=256M %s " . $backupInstanceCommand->getName() . " %s\n",
            $minutes,
            $hours,
            $managerPath,
            PHP_BINARY,
            $_ENV['TIKI_MANAGER_EXECUTABLE'],
            $arguments
        );

        file_put_contents($file = $_ENV['TEMP_FOLDER'] . '/crontab', `crontab -l` . $entry);

        $this->io->newLine();
        $this->io->note('If adding to crontab fails and blocks, hit Ctrl-C and add these parameters manually.');
        $this->io->text($entry);

        `crontab $file`;

        return 0;
    }
}
