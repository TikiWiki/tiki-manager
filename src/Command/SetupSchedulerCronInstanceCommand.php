<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;

class SetupSchedulerCronInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:setup-scheduler-cron')
            ->setDescription('Setup instance\'s scheduler cron')
            ->setHelp('This command allows you to enable the cron to run the schedulers')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_OPTIONAL,
                'List of instance IDs to be checked, separated by comma (,)'
            )
            ->addOption(
                'time',
                't',
                InputOption::VALUE_REQUIRED,
                'Cron time string',
                '* * * * *'
            )
            ->addOption(
                'update',
                'u',
                InputOption::VALUE_NONE,
                'Update existing configured cron'
            )
            ->addOption(
                'check',
                'c',
                InputOption::VALUE_NONE,
                'Check if there is cronjob configured'
            )
            ->addOption(
                'enable',
                null,
                InputOption::VALUE_NONE,
                'Enable existing cronjob'
            )
            ->addOption(
                'disable',
                null,
                InputOption::VALUE_NONE,
                'Disable existing cronjob'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            return;
        }

        if (empty($input->getOption('instances'))) {
            $this->io->newLine();
            $output->writeln('<comment>In case you want to configure more than one instance, please use a comma (,) between the values</comment>');

            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $selectedInstances = $this->io->ask(
                'Which instance(s) do you want to setup scheduler cron?',
                null,
                function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                }
            );

            $selectedInstances = implode(',', CommandHelper::getInstanceIds($selectedInstances));

            $input->setOption('instances', $selectedInstances);
        }

        $check = $input->getOption('check');
        $changeStatus = $input->getOption('enable') ? 'enable' : ($input->getOption('disable') ? 'disable' : null);
        if ($check || $changeStatus || $input->getOption('time') != '* * * * *') {
            return;
        }

        $cronFreq = $this->io->choice('What is the cron frequency?', [
            'Every minute (* * * * *)',
            'Every half hour (*/30 * * * *)',
            'Every hour (0 * * * *)',
            'Custom'
        ], 0);

        if ($cronFreq === 'Custom') {
            $cronFreq = $this->io->ask('Please provide a cron time expression', null, function ($answer) {
                return Helper\CommandHelper::validateCrontabInput($answer);
            });
        } else {
            preg_match('/\((.+)\)/', $cronFreq, $matches);
            $cronFreq = $matches[1];
        }

        $input->setOption('time', $cronFreq);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (!isset($instancesInfo)) {
            $output->writeln('<comment>No instances available to configure.</comment>');
            return 0;
        }

        $instancesOption = $input->getOption('instances');

        $selectedInstances = CommandHelper::validateInstanceSelection($instancesOption, $instances);
        $time = $input->getOption('time');
        Helper\CommandHelper::validateCrontabInput($time);

        $hookName = $this->getCommandHook();
        foreach ($selectedInstances as $instance) {
            try {
                $cronJob = $this->handleInstance($instance, $input);
                $this->writeCronJobStatus($cronJob);
                $hookName->registerPostHookVars(['instance' => $instance, 'cron' => $cronJob]);
            } catch (\Exception $e) {
                $this->io->writeln('<error>'. $e->getMessage() . '</error>');
            }

            $this->io->newLine();
        }

        return 0;
    }

    protected function handleInstance(Instance $instance, InputInterface $input): Instance\CronJob
    {
        $this->io->section($instance->name);

        $instanceOS = $instance->getDiscovery()->detectOS();
        if ($instanceOS === 'WINDOWS' || $instanceOS === 'WINNT') {
            throw new \Exception('Operating System not supported.');
        }

        $update = $input->getOption('update');
        $check = $input->getOption('check');
        $changeStatus = $input->getOption('enable') ? 'enable' : ($input->getOption('disable') ? 'disable' : null);
        $time = $input->getOption('time');

        $cronManager = $instance->getCronManager();
        $cronJob = $cronManager->getConsoleCommandJob('scheduler:run');

        if ($check) {
            return $cronJob;
        }

        if (!$cronJob) {
            $newCronJob = $cronManager->createConsoleCommandJob($time, 'scheduler:run');
            $cronManager->addJob($newCronJob);
            $this->io->info('Job created');
            return $newCronJob;
        }

        if (!$update && !$changeStatus) {
            $this->io->info('There is a job already configured, please use --update to update the existing job. Skipping...');
            return $cronJob;
        }

        $newCronJob = $cronManager->createConsoleCommandJob($time, 'scheduler:run');

        if ($changeStatus) {
            $newCronJob = clone $cronJob;
            $newCronJob->$changeStatus();

            if ($cronJob->isEnabled() == $newCronJob->isEnabled()) {
                $this->io->info('Existing job is already ' . $changeStatus . 'd. Skipping...');
                return $newCronJob;
            }
        }

        $cronManager->replaceJob($cronJob, $newCronJob);
        $this->io->info('Job updated.');

        return $newCronJob;
    }

    protected function writeCronJobStatus(Instance\CronJob $job = null)
    {
        if (!$job) {
            $message = <<<TXT
Cron Job:
  - Status: <error>%s</error>
TXT;
            $output = sprintf(
                $message,
                'No cronjob was found'
            );
        } else {
            $statusMsg = !$job->isEnabled() ? '<error>Disabled</error>' : '<info>Enabled</info>';
            $message = <<<TXT
Cron Job:
  - Status: %s
  - Time: %s
  - Command: %s
TXT;

            $output = sprintf(
                $message,
                $statusMsg,
                $job->getTime(),
                $job->getCommand()
            );
        }

        $this->io->writeln($output);
    }
}
