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
use Symfony\Component\Process\Process;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\Environment;

/**
 * Setup automatic instance clone using CRON
 * Class SetupCloneCommand
 * @package TikiManager\Command
 */
class SetupCloneManagerCommand extends TikiManagerCommand
{
    /**
     * Command configuration function
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('manager:setup-clone')
            ->setDescription('Setup a cronjob to perform instance clone')
            ->setHelp('This command allows you setup a cronjob to perform another identical copy of Tiki ')
            ->setAliases(['setup:clone'])
            ->addOption(
                'time',
                null,
                InputOption::VALUE_REQUIRED,
                'A cron time expression of when clone should be triggered'
            )
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check files checksum after operation has been performed.'
            )
            ->addOption(
                'upgrade',
                null,
                InputOption::VALUE_REQUIRED,
                'Upgrade Instance after Clone'
            )
            ->addOption(
                'source',
                's',
                InputOption::VALUE_REQUIRED,
                'Source instance.'
            )
            ->addOption(
                'target',
                't',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Destination instance(s).'
            )
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Select Branch.'
            )
            ->addOption(
                'skip-reindex',
                null,
                InputOption::VALUE_NONE,
                'Skip rebuilding index step.'
            )
            ->addOption(
                'skip-cache-warmup',
                null,
                InputOption::VALUE_NONE,
                'Skip generating cache step.'
            )
            ->addOption(
                'warmup-include-modules',
                null,
                InputOption::VALUE_NONE,
                'Include modules in cache warmup (default is only templates and misc).'
            )
            ->addOption(
                'live-reindex',
                null,
                InputOption::VALUE_OPTIONAL,
                'Live reindex, set instance maintenance off and after perform index rebuild.',
                true
            )
            ->addOption(
                'direct',
                'd',
                InputOption::VALUE_NONE,
                'Prevent using the backup step and rsync source to target.'
            )
            ->addOption(
                'keep-backup',
                null,
                InputOption::VALUE_NONE,
                'Source instance backup is not deleted before the process finished.'
            )
            ->addOption(
                'use-last-backup',
                null,
                InputOption::VALUE_NONE,
                'Use source instance last created backup.'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('time'))) {
            $answer = $this->io->ask('What time should it run at?', '0 0 * * *', function ($answer) {
                return CommandHelper::validateCrontabInput($answer);
            });

            $input->setOption('time', $answer);
        }

        if (empty($input->getOption('upgrade'))) {
            $answer = $this->io->confirm('Would you want to upgrade instance after clone?');
            $input->setOption('upgrade', $answer);
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
        // select time handle
        $time = $input->getOption('time');
        CommandHelper::validateCrontabInput($time);

        // check for upgrade mode
        $argumentToAdd = 'upgrade';
        if (!$input->getOption('upgrade')) {
            $argumentToAdd = '';
        }

        if ($input->getOption('upgrade')) {
            $instances = CommandHelper::getInstances('upgrade');
        } else {
            $instances = CommandHelper::getInstances('all', true);
        }
        // check for availability of instance
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available to clone/clone and upgrade.</comment>');
            return 0;
        }
        $helper = $this->getHelper('question');

        // select source instance
        if (empty($input->getOption('source'))) {
            $this->io->newLine();
            $output->writeln('<comment>NOTE: Clone operations are only available on Local and SSH instances.</comment>');
            if ($input->getOption('upgrade') && $instancesInfo) {
                $output->writeln('<comment>Some instances are not upgradeable and thus, they are not listed here.</comment>');
            }

            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $question = CommandHelper::getQuestion('Select the source instance', null);
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances, CommandHelper::INSTANCE_SELECTION_SINGLE);
            });

            $selectedSourceInstances = $helper->ask($input, $output, $question);
        } else {
            $selectedSourceInstances = CommandHelper::validateInstanceSelection($input->getOption('source'), $instances, CommandHelper::INSTANCE_SELECTION_SINGLE);
        }

        $sourceInstance = reset($selectedSourceInstances);
        $input->setOption('source', $sourceInstance->getId());

        // select target instance
        $instances = CommandHelper::getInstances('all');
        $instances = array_filter($instances, function ($instance) use ($sourceInstance) {
            return $instance->getId() != $sourceInstance->getId();
        });
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available as destination.</comment>');
            return 0;
        }

        if (empty($input->getOption('target'))) {
            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $question = CommandHelper::getQuestion('Select the destination instance(s)', null);
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedDestinationInstances = $helper->ask($input, $output, $question);
        } else {
            $selectedDestinationInstances = CommandHelper::validateInstanceSelection($input->getOption('target'), $instances);
        }
        $input->setOption('target', implode(',', CommandHelper::getInstanceIds($selectedDestinationInstances)));

        // select branch if upgrade
        if (!empty($argumentToAdd)) {
            if (empty($input->getOption('branch'))) {
                $branch = $this->getUpgradeVersion($sourceInstance);
                $input->setOption('branch', $branch);
            }
        }

        if ($input->getOption('direct') && ($input->getOption('keep-backup')|| $input->getOption('use-last-backup'))) {
            $this->io->error('The options --direct and --keep-backup or --use-last-backup could not be used in conjunction, instance filesystem is not in the backup file.');
            exit(-1);
        }

        // Create command line
        $managerPath = realpath(dirname(__FILE__) . '/../..');
        $cloneInstance = new CloneInstanceCommand();
        $cloneInstanceCommand = Environment::get('TIKI_MANAGER_EXECUTABLE') . ' ' . $cloneInstance->getName().' ' .$argumentToAdd.' --no-interaction ';
        if ($checksumCheck = $input->getOption('check')) {
            $cloneInstanceCommand .= ' --check='.$checksumCheck;
        }

        if ($source = $input->getOption('source')) {
            $cloneInstanceCommand .= ' --source=' . $source;
        }

        if ($target = $input->getOption('target')) {
            $cloneInstanceCommand .= ' --target=' . $target;
        }

        if ($branch = $input->getOption('branch')) {
            $cloneInstanceCommand .= ' --branch=' . $branch;
        }

        if ($skipReindex = $input->getOption('skip-reindex')) {
            $cloneInstanceCommand .= ' --skip-reindex=' . $skipReindex;
        }

        if ($skipCacheWarmup = $input->getOption('skip-cache-warmup')) {
            $cloneInstanceCommand .= ' --skip-cache-warmup=' . $skipCacheWarmup;
        }

        if ($warmupIncludeModules = $input->getOption('warmup-include-modules')) {
            $cloneInstanceCommand .= ' --warmup-include-modules=' . $warmupIncludeModules;
        }

        if ($liveReindex = is_null($input->getOption('live-reindex')) ? true : filter_var($input->getOption('live-reindex'), FILTER_VALIDATE_BOOLEAN)) {
            $cloneInstanceCommand .= ' --live-reindex=' . $liveReindex;
        }

        if ($direct = $input->getOption('direct')) {
            $cloneInstanceCommand .= ' --direct=' . $direct;
        }

        if ($keepBackup = $input->getOption('keep-backup')) {
            $cloneInstanceCommand .= ' --keep-backup=' . $keepBackup;
        }

        if ($useLastBackup = $input->getOption('use-last-backup')) {
            $cloneInstanceCommand .= ' --use-last-backup=' . $useLastBackup;
        }

        $entry = sprintf(
            "%s cd %s && %s %s\n",
            $time,
            $managerPath,
            PHP_BINARY,
            $cloneInstanceCommand
        );

        $this->io->newLine();

        // Write cron in the crontab file
        $tempFile = Environment::get('TEMP_FOLDER') . '/crontab';
        $currentCronProcess = Process::fromShellCommandline("crontab -l", null, null, null, 1800);
        $currentCronProcess->run();
        $currentCron = $currentCronProcess->getOutput();
        $error = $currentCronProcess->getErrorOutput();
        $exitCode = $currentCronProcess->getExitCode();
        if ($exitCode !== 0) {
            $this->io->error("Error: Failed to getting list of cron jobs");
            return 1;
        }
        $cronData = explode("\n\n", $currentCron);
        $cronJobExists = false;
        foreach ($cronData as $cronJob) {
            if (strpos($cronJob, $entry) !== false) {
                $cronJobExists = true;
                break;
            }
        }
        if ($cronJobExists) {
            $this->io->writeln("<comment>Entry is already there, so we will not add a duplicate: \n{$entry} </comment>");
            return 1;
        }
        if (file_put_contents($tempFile, $currentCron . PHP_EOL . $entry)) {
            $cronTab = Process::fromShellCommandline('crontab ' .$tempFile, null, null, null, 1800);
            $cronTab->run();
            $cronTabOutput = $cronTab->getOutput();
            $error = $cronTab->getErrorOutput();
            $exitCode = $cronTab->getExitCode();
            if ($exitCode !== 0) {
                $this->io->error("Failed to edit crontab file. Please add the following line to your crontab file: \n{$entry}");
                return 1;
            }
        }

        $verifyCronProcess = Process::fromShellCommandline("crontab -l", null, null, null, 1800);
        $verifyCronProcess->run();
        $updatedCron = $verifyCronProcess->getOutput();

        if (strpos($updatedCron, $entry) !== false) {
            $this->io->success('Cronjob configured and installed successfully.');
            return 0;
        } else {
            $this->io->error("Failed to validate the crontab entry. Please manually check the following command: \n{$entry}");
            return 1;
        }
    }

    /**
     * Get version to update instance to
     *
     * @param Instance $instance
     * @return string
     */
    private function getUpgradeVersion($instance)
    {
        $found_incompatibilities = false;
        $instance->detectPHP();

        $app = $instance->getApplication();
        $versions = $app->getVersions();
        $choices = [];

        foreach ($versions as $key => $version) {
            preg_match('/(\d+\.|master)/', $version->branch, $matches);
            if (array_key_exists(0, $matches)) {
                if ((($matches[0] >= 13) || ($matches[0] == 'master')) &&
                    ($instance->phpversion < 50500)) {
                    // Nothing to do, this match is incompatible...
                    $found_incompatibilities = true;
                } else {
                    $choices[] = $version->type . ' : ' . $version->branch;
                }
            }
        }

        $this->logger->info('Detected PHP release: {php_version}', ['php_version' => CommandHelper::formatPhpVersion($instance->phpversion)]);

        if ($found_incompatibilities) {
            $this->io->writeln('<comment>If some versions are not offered, it\'s likely because the host</comment>');
            $this->io->writeln('<comment>server doesn\'t meet the requirements for that version (ex: PHP version is too old)</comment>');
        }

        $choice = $this->io->choice('Which version do you want to update to', $choices);
        $choice = explode(':', $choice);
        return trim($choice[1]);
    }
}
