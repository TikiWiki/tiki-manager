<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;

/**
 * Setup automatic instance updates using CRON
 * Class SetupUpdateCommand
 * @package TikiManager\Command
 */
class SetupUpdateCommand extends Command
{
    /**
     * Command configuration function
     */
    protected function configure()
    {
        $this
            ->setName('manager:setup-update')
            ->setDescription('Setup automatic instance updates')
            ->setHelp('This command allows you setup automatic instance updates')
            ->addOption(
                'time',
                null,
                InputOption::VALUE_REQUIRED,
                'The time update should be triggered'
            )
            ->addOption(
                'instances',
                null,
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be updated, separated by comma (,)'
            )
            ->addOption(
                'email',
                null,
                InputOption::VALUE_REQUIRED,
                'Email address to report update failures (multiple emails must be separated by comma (,)).'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (empty($input->getOption('time'))) {
            $helper = $this->getHelper('question');
            $answer = $io->ask('What time should it run at?', '00:00', function ($answer) {
                return CommandHelper::validateTimeInput($answer);
            });

            $input->setOption('time', implode(':', $answer));
        }

        $instances = CommandHelper::getInstances('update');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($input->getOption('instances'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $io->newLine();
            $io->writeln('<comment>In case you want to update more than one instance, please use a comma (,) between the values</comment>');

            $answer = $io->ask('Which instance(s) do you want to update?', null, function ($answer) use ($instances) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                return implode(',', CommandHelper::getInstanceIds($selectedInstances));
            });

            $input->setOption('instances', $answer);
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

    /**
     * Command handler function
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $time = $input->getOption('time');
        // Check if option (set in cli is also valid)
        list($hours, $minutes) = CommandHelper::validateTimeInput($time);

        $instancesOption = $input->getOption('instances');
        // Check if option (set in cli is also valid)
        $instances = CommandHelper::getInstances('update');
        CommandHelper::validateInstanceSelection($instancesOption, $instances);

        $email = $input->getOption('email');
        $email = CommandHelper::validateEmailInput($email);

        $managerPath = realpath(dirname(__FILE__) . '/../..');

        $updateInstance = new UpdateInstanceCommand();
        $updateInstanceCommand = TIKI_MANAGER_EXECUTABLE . ' ' . $updateInstance->getName() . ' --no-interaction --instances=' . $instancesOption;
        if (!empty($email)) {
            $updateInstanceCommand .= ' --email=' . $email;
        }
        $entry = sprintf(
            "%d %d * * * cd %s && %s %s\n",
            $minutes,
            $hours,
            $managerPath,
            PHP_BINARY,
            $updateInstanceCommand
        );

        file_put_contents($file = TEMP_FOLDER . '/crontab', `crontab -l` . $entry);
        $io->writeln("\n<fg=cyan>If adding to crontab fails and blocks, hit Ctrl-C and add these parameters manually.</>");
        $io->writeln("<fg=cyan>\t$entry</>");
        `crontab $file`;
    }
}
