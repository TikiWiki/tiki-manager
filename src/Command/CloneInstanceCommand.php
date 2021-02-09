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
use Symfony\Component\Console\Input\InputArgument;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\InstanceConfigure;
use TikiManager\Libs\Database\Database;
use TikiManager\Libs\Helpers\VersionControl;

class CloneInstanceCommand extends TikiManagerCommand
{
    use InstanceConfigure;

    protected function configure()
    {
        $this
            ->setName('instance:clone')
            ->setDescription('Clone instance')
            ->setHelp('This command allows you make another identical copy of Tiki')
            ->addArgument('mode', InputArgument::IS_ARRAY | InputArgument::OPTIONAL)
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check files checksum after operation has been performed. (Only in upgrade mode)'
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
                'Skip rebuilding index step. (Only in upgrade mode).'
            )
            ->addOption(
                'skip-cache-warmup',
                null,
                InputOption::VALUE_NONE,
                'Skip generating cache step. (Only in upgrade mode).'
            )
            ->addOption(
                'live-reindex',
                null,
                InputOption::VALUE_OPTIONAL,
                'Live reindex, set instance maintenance off and after perform index rebuild. (Only in upgrade mode)',
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
            )->addOption(
                'db-host',
                'dh',
                InputOption::VALUE_REQUIRED,
                'Target instance database host'
            )
            ->addOption(
                'db-user',
                'du',
                InputOption::VALUE_REQUIRED,
                'Target instance database user'
            )
            ->addOption(
                'db-pass',
                'dp',
                InputOption::VALUE_REQUIRED,
                'Target instance database password'
            )
            ->addOption(
                'db-prefix',
                'dpx',
                InputOption::VALUE_REQUIRED,
                'Target instance database prefix'
            )
            ->addOption(
                'db-name',
                'dn',
                InputOption::VALUE_REQUIRED,
                'Target instance database name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available to clone/clone and upgrade.</comment>');
            return 0;
        }

        $helper = $this->getHelper('question');

        $clone = false;
        $cloneUpgrade = false;
        $offset = 0;

        $checksumCheck = $input->getOption('check');
        $skipReindex = $input->getOption('skip-reindex');
        $skipCache = $input->getOption('skip-cache-warmup');
        $liveReindex = is_null($input->getOption('live-reindex')) ? true : filter_var($input->getOption('live-reindex'), FILTER_VALIDATE_BOOLEAN);
        $direct = $input->getOption('direct');
        $keepBackup = $input->getOption('keep-backup');
        $useLastBackup = $input->getOption('use-last-backup');
        $argument = $input->getArgument('mode');

        $setupTargetDatabase = (bool) ($input->getOption('db-prefix') || $input->getOption('db-name'));

        if ($direct && ($keepBackup || $useLastBackup)) {
            $this->io->error('The options --direct and --keep-backup or --use-last-backup could not be used in conjunction, instance filesystem is not in the backup file.');
            exit(-1);
        }

        if (isset($argument) && !empty($argument)) {
            if (is_array($argument)) {
                $clone = $input->getArgument('mode')[0] == 'clone';
                $cloneUpgrade = $input->getArgument('mode')[0] == 'upgrade';
            } else {
                $cloneUpgrade = $input->getArgument('mode') == 'upgrade';
            }
        }

        if ($clone != false || $cloneUpgrade != false) {
            $offset = 1;
        }

        $arguments = array_slice($input->getArgument('mode'), $offset);
        if (!empty($arguments[0])) {
            $selectedSourceInstances = getEntries($instances, $arguments[0]);
        } elseif ($sourceOption = $input->getOption("source")) {
            $selectedSourceInstances = CommandHelper::validateInstanceSelection($sourceOption, $instances);
        } else {
            $this->io->newLine();
            $output->writeln('<comment>NOTE: Clone operations are only available on Local and SSH instances.</comment>');

            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $question = CommandHelper::getQuestion('Select the source instance', null);
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedSourceInstances = $helper->ask($input, $output, $question);
        }

        $sourceInstance = $selectedSourceInstances[0];
        $instances = CommandHelper::getInstances('all');

        $instances = array_filter($instances, function ($instance) use ($sourceInstance) {
            return $instance->getId() != $sourceInstance->getId();
        });

        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available as destination.</comment>');
            return 0;
        }

        if (!empty($arguments[1])) {
            $selectedDestinationInstances = getEntries($instances, $arguments[1]);
        } elseif ($targetOption = implode(',', $input->getOption("target"))) {
            $selectedDestinationInstances = CommandHelper::validateInstanceSelection($targetOption, $instances);
        } else {
            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $question = CommandHelper::getQuestion('Select the destination instance(s)', null);
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedDestinationInstances = $helper->ask($input, $output, $question);
        }

        if ($setupTargetDatabase && count($selectedDestinationInstances) > 1) {
            $this->io->error('Database setup options can only be used when a single target instance is passed.');
            return 1;
        }

        if ($cloneUpgrade) {
            if (!empty($arguments[2])) {
                $input->setOption('branch', $arguments[2]);
            } else {
                $branch = $input->getOption('branch');
                if (empty($branch)) {
                    $branch = $this->getUpgradeVersion($sourceInstance);
                    $input->setOption('branch', $branch);
                }
            }
        }

        // PRE-CHECK
        $this->io->newLine();
        $this->io->section('Pre-check');

        $directWarnMessage = 'Direct backup cannot be used, instance {instance_name} is not local. Only supported on local instances.';
        // Check if direct flag can be used
        if ($direct && $sourceInstance->type != 'local') {
            $direct = false;
            $this->logger->warning($directWarnMessage, ['instance_name' => $sourceInstance->name]);
        }

        if ($direct) {
            foreach ($selectedDestinationInstances as $destinationInstance) {
                if ($destinationInstance->type == 'local') {
                    continue;
                }

                $this->logger->warning($directWarnMessage, ['instance_name' => $destinationInstance->name]);
                $direct = false;
                break;
            }
        }

        $dbConfigErrorMessage = 'Unable to load/set database configuration for instance {instance_name} (id: {instance_id}). {exception_message}';
        try {
            // The source instance needs to be well configured by default
            if (!$this->testExistingDbConnection($sourceInstance)) {
                throw new \Exception('Existing database configuration failed to connect.');
            }
        } catch (\Exception $e) {
            $this->logger->error($dbConfigErrorMessage, [
                'instance_name' => $sourceInstance->name,
                'instance_id' => $sourceInstance->getId(),
                'exception_message' => $e->getMessage(),
            ]);
            return 1;
        }

        foreach ($selectedDestinationInstances as $key => $destinationInstance) {
            try {
                $destinationInstance->app = $sourceInstance->app; // Required to setup database connection

                if (!$setupTargetDatabase && !$this->input->isInteractive() &&
                    !$this->testExistingDbConnection($destinationInstance)) {
                    throw new \Exception('Existing database configuration failed to connect.');
                }

                $this->setupDatabase($destinationInstance, $setupTargetDatabase);
                $destinationInstance->database()->setupConnection();
            } catch (\Exception $e) {
                $this->logger->error($dbConfigErrorMessage, [
                    'instance_name' => $destinationInstance->name,
                    'instance_id' => $destinationInstance->getId(),
                    'exception_message' => $e->getMessage(),
                ]);
                unset($selectedDestinationInstances[$key]);
                continue;
            }

            if ($this->isSameDatabase($sourceInstance, $destinationInstance)) {
                $this->logger->error('Database host and name are the same in the source ({source_instance_name}) and destination ({target_instance_id}).', [
                    'source_instance_name' => $sourceInstance->name,
                    'target_instance_id' => $destinationInstance->name
                ]);
                unset($selectedDestinationInstances[$key]);
                continue;
            }
        }

        if (empty($selectedDestinationInstances)) {
            $this->logger->error('No valid instances to continue the clone process.');
            return 1;
        }

        $archive = '';
        $standardProcess = true;
        if ($useLastBackup) {
            $standardProcess = false;
            $archiveDir = rtrim($_ENV['ARCHIVE_FOLDER'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $archiveDir .= sprintf('%s-%s', $sourceInstance->id, $sourceInstance->name);

            if (file_exists($archiveDir)) {
                $archiveFiles = array_diff(scandir($archiveDir, SCANDIR_SORT_DESCENDING), ['.', '..']);
                if (! empty($archiveFiles[0])) {
                    $archive = $archiveDir . DIRECTORY_SEPARATOR . $archiveFiles[0];
                    $this->logger->notice('Using last created backup ({backup_name}) of {instance_name}', [
                        'instance_name' => $sourceInstance->name,
                        'backup_name' => $archiveFiles[0]
                    ]);
                    $keepBackup = true;
                } else {
                    $this->logger->error('Backups not found for instance {instance_name}', ['instance_name' => $sourceInstance->name]);
                    $standardProcess = $this->io->confirm('Continue with standard process?', true);

                    if (!$standardProcess) {
                        $this->io->writeln('Clone process aborted.');
                        exit(-1);
                    }
                }
            }
        }

        // SNAPSHOT SOURCE INSTANCE
        if ($standardProcess) {
            $this->io->newLine();
            $this->io->section('Creating snapshot of: ' . $sourceInstance->name);
            try {
                $archive = $sourceInstance->backup($direct);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        if (empty($archive)) {
            $this->logger->error('Snapshot creation failed.');
            exit(-1);
        }

        /** @var Instance $destinationInstance */
        foreach ($selectedDestinationInstances as $destinationInstance) {
            $this->io->newLine();
            $this->io->section('Initiating clone of ' . $sourceInstance->name . ' to ' . $destinationInstance->name);

            $destinationInstance->lock();
            $destinationInstance->restore($sourceInstance, $archive, true, $checksumCheck, $direct);

            if ($cloneUpgrade) {
                $branch = $input->getOption('branch');
                $branch = VersionControl::formatBranch($branch, $destinationInstance->vcs_type);
                $upgrade_version = Version::buildFake($destinationInstance->vcs_type, $branch);

                $output->writeln('<fg=cyan>Upgrading to version ' . $upgrade_version->branch . '</>');
                $app = $destinationInstance->getApplication();

                try {
                    $app->performUpgrade($destinationInstance, $upgrade_version, [
                        'checksum-check' => $checksumCheck,
                        'skip-reindex' => $skipReindex,
                        'skip-cache-warmup' => $skipCache,
                        'live-reindex' => $liveReindex
                    ]);
                } catch (\Exception $e) {
                    CommandHelper::setInstanceSetupError($destinationInstance->id, $e);
                    continue;
                }
            }
            if ($destinationInstance->isLocked()) {
                $destinationInstance->unlock();
            }
        }

        if (!$keepBackup && !$direct) {
            $output->writeln('Deleting archive...');
            $access = $sourceInstance->getBestAccess('scripting');
            $access->shellExec("rm -f " . $archive);
        }

        $this->io->newLine();
        $this->logger->info('Finished');
        return 0;
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
            preg_match('/(\d+\.|trunk|master)/', $version->branch, $matches);
            if (array_key_exists(0, $matches)) {
                if ((($matches[0] >= 13) || ($matches[0] == 'trunk') || ($matches[0] == 'master')) &&
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

    /**
     * @param Instance $source
     * @param Instance $target
     * @return bool
     */
    public function isSameDatabase(Instance $source, Instance $target): bool
    {
        $sourceAccess = $source->getBestAccess();
        $targetAccess = $target->getBestAccess();
        $sourceDB = $source->getDatabaseConfig();
        $targetDB = $target->getDatabaseConfig();

        return (($sourceAccess->host == $targetAccess->host ||
                ($sourceAccess->host != $targetAccess->host && !in_array($targetDB->host, ['127.0.0.1', 'localhost'])))
            && Database::compareDatabase($sourceDB, $targetDB));
    }
}
