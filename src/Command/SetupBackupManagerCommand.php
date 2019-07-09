<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;

class SetupBackupManagerCommand extends Command
{
    protected function configure()
    {
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
                'ex',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be excluded, separated by comma (,)'
            )
            ->addOption(
                'email',
                null,
                InputOption::VALUE_REQUIRED,
                'Email address to report backup failures (multiple emails must be separated by comma (,)).'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        if (empty($input->getOption('time'))) {
            $helper = $this->getHelper('question');
            $answer = $io->ask('What time should it run at?', '00:00', function ($answer) {
                return CommandHelper::validateTimeInput($answer);
            });

            $input->setOption('time', implode(':', $answer));
        }

        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (isset($instancesInfo) && empty($input->getOption('exclude'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $io->newLine();
            $io->writeln('<comment>In case you want to ignore more than one instance, please use a comma (,) between the values</comment>');

            $answer = $io->ask('Which instance IDs should be ignored?', null, function ($answer) use ($instances) {
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
            $io->error($e->getMessage());
            $email = null;
        }

        if (empty($email)) {
            $email = $io->ask('[Optional] Email address to contact in case of failures (use , to separate multiple emails)', null, CommandHelper::validateEmailInput($value));
            $input->setOption('email', $email);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $time = $input->getOption('time');
        list($hours, $minutes) = CommandHelper::validateTimeInput($time);
        $arguments = 'all --no-interaction';
        $excludedInstances = $input->getOption('exclude');

        if (! empty($excludedInstances)) {
            $arguments .= ' --exclude=' . $excludedInstances;
        }

        $email = $input->getOption('email');
        $email = CommandHelper::validateEmailInput($email);

        if (!empty($email)) {
            $arguments .= ' --email=' . $email;
        }

        $backupInstanceCommand = new BackupInstanceCommand();
        $managerPath = realpath(dirname(__FILE__) . '/../..');
        $entry = sprintf(
            "%d %d * * * cd %s && %s -d memory_limit=256M %s " . $backupInstanceCommand->getName() . " %s\n",
            $minutes,
            $hours,
            $managerPath,
            PHP_BINARY,
            TIKI_MANAGER_EXECUTABLE,
            $arguments
        );

        file_put_contents($file = TEMP_FOLDER . '/crontab', `crontab -l` . $entry);

        $io->newLine();
        $io->note('If adding to crontab fails and blocks, hit Ctrl-C and add these parameters manually.');
        $io->text($entry);

        `crontab $file`;
    }
}
