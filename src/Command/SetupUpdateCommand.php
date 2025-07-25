<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;

/**
 * Setup automatic instance updates using CRON
 * Class SetupUpdateCommand
 * @package TikiManager\Command
 */
class SetupUpdateCommand extends TikiManagerCommand
{
    /**
     * Command configuration function
     */
    protected function configure()
    {
        parent::configure();

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
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs (or names) to be updated, separated by comma (,). You can also use the "all" keyword.'
            )
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Email address to report update failures (multiple emails must be separated by comma (,)).'
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

        $instances = CommandHelper::getInstances('update');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($input->getOption('instances'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $this->io->newLine();
            $this->io->writeln('<comment>In case you want to update more than one instance, please use a comma (,) between the values</comment>');

            $answer = $this->io->ask('Which instance(s) do you want to update?', null, function ($answer) use ($instances) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                return implode(',', CommandHelper::getInstanceIds($selectedInstances));
            });

            $input->setOption('instances', $answer);
        }

        if (empty($input->getOption('email'))) {
            $email = $this->io->ask(
                '[Optional] Email address to contact in case of failures (use , to separate multiple emails)',
                null,
                function ($value) {
                    return CommandHelper::validateEmailInput($value);
                }
            );
            $input->setOption('email', $email);
        }
    }

    /**
     * Command handler function
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $time = $input->getOption('time');
        // Check if option (set in cli is also valid)
        list($hours, $minutes) = CommandHelper::validateTimeInput($time);

        $instancesOption = $input->getOption('instances');
        // Check if option (set in cli is also valid)
        $instances = CommandHelper::getInstances('update');
        $selectedInstances = CommandHelper::validateInstanceSelection($instancesOption, $instances);

        // If the value was "all", we MUST keep "all"!! and not change to IDs.
        // Otherwise, the crontab will only handle instance that existed at the time the command was used.
        // Else, we will replace by numerical IDs, to prevent any injection in the crontab parameters.
        if ($instancesOption !== 'all') {
            $instancesOption = implode(',', CommandHelper::getInstanceIds($selectedInstances));
        }

        $email = $input->getOption('email');
        $email = CommandHelper::validateEmailInput($email);

        $managerPath = realpath(dirname(__FILE__) . '/../..');

        $updateInstance = new UpdateInstanceCommand();
        $updateInstanceCommand = $_ENV['TIKI_MANAGER_EXECUTABLE'] . ' ' . $updateInstance->getName() . ' --no-interaction --instances=' . $instancesOption;
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

        file_put_contents($file = $_ENV['TEMP_FOLDER'] . '/crontab', `crontab -l` . $entry);
        $this->io->writeln("\n<fg=cyan>If adding to crontab fails and blocks, hit Ctrl-C and add these parameters manually.</>");
        $this->io->writeln("<fg=cyan>\t$entry</>");
        `crontab $file`;

        return Command::SUCCESS;
    }
}
