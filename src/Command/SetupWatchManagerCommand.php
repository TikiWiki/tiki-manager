<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\App;

class SetupWatchManagerCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('manager:setup-watch')
            ->setDescription('Set-up cron job to perform hash checks')
            ->setHelp('This command allows you to set-up a cron job on the Tiki Manager master to perform the Hash check automatically every day.')
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Email address to contact.'
            )
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
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $email = $input->getOption('email');
        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $question = CommandHelper::getQuestion('Email address to contact');
            $question->setValidator(function ($value) {
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Please insert a valid email address.');
                }
                return $value;
            });
            $email = $helper->ask($input, $output, $question);
        }
        $input->setOption('email', $email);

        if (empty($input->getOption('time'))) {
            $helper = $this->getHelper('question');
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

            $answer = $this->io->ask('Which instance IDs (or names) should be ignored?', null, function ($answer) use ($instances) {
                $excludeInstance = '';
                if (! empty($answer)) {
                    $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                    $excludeInstance = implode(',', CommandHelper::getInstanceIds($selectedInstances));
                }
                return $excludeInstance;
            });

            $input->setOption('exclude', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getOption('email');

        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidOptionException('Email cannot be empty');
        }

        $time = $input->getOption('time');

        // Check if option (set in cli is also valid)
        list($hours, $minutes) = CommandHelper::validateTimeInput($time);

        $arguments = '--email=' . $email . ' --no-interaction';

        $excludedInstances = $input->getOption('exclude');
        if (! empty($excludedInstances)) {
            $arguments .= ' --exclude=' . $excludedInstances;
        }

        $managerPath = realpath(dirname(__FILE__) . '/../..');
        $entry = sprintf(
            "%d %d * * * cd %s && %s -d memory_limit=256M %s instance:watch %s\n",
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
