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
use TikiManager\Application\Tiki\Versions\Fetcher\YamlFetcher;
use TikiManager\Application\Tiki\Versions\TikiRequirementsHelper;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\InstanceConfigure;
use TikiManager\Command\Traits\InstanceUpgrade;
use TikiManager\Config\Environment;
use TikiManager\Libs\Database\Database;
use TikiManager\Libs\Helpers\VersionControl;

class CloneInstanceCommand extends TikiManagerCommand
{
    use InstanceConfigure;
    use InstanceUpgrade;

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
            )
            ->addOption(
                'stash',
                null,
                InputOption::VALUE_NONE,
                'Only on Git: saves your local modifications, and try to apply after update/upgrade'
            )
            ->addOption(
                'timeout',
                null,
                InputOption::VALUE_OPTIONAL,
                'Modify the default command execution timeout from 3600 seconds to a custom value'
            )
            ->addOption(
                'ignore-requirements',
                null,
                InputOption::VALUE_NONE,
                'Ignore version requirements. Allows to select non-supported branches, useful for testing.'
            )
            ->addOption(
                'only-data',
                null,
                InputOption::VALUE_NONE,
                'Clone only database and data files. Skip cloning code.'
            )
            ->addOption(
                'only-code',
                null,
                InputOption::VALUE_NONE,
                'Clone only code files. Skip cloning database.'
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
        $onlyData = $input->getOption('only-data');
        $onlyCode = $input->getOption('only-code');
        $vcsOptions = [
            'allow_stash' => $input->getOption('stash')
        ];
        $timeout = $input->getOption('timeout') ?: Environment::get('COMMAND_EXECUTION_TIMEOUT');
        $_ENV['COMMAND_EXECUTION_TIMEOUT'] = $timeout;

        $setupTargetDatabase = (bool) ($input->getOption('db-prefix') || $input->getOption('db-name'));

        if ($direct && ($keepBackup || $useLastBackup)) {
            $this->io->error('The options --direct and --keep-backup or --use-last-backup could not be used in conjunction, instance filesystem is not in the backup file.');
            return 1;
        }

        if (isset($argument) && !empty($argument)) {
            if (is_array($argument)) {
                $clone = $input->getArgument('mode')[0] == 'clone';
                $cloneUpgrade = $input->getArgument('mode')[0] == 'upgrade';
            } else {
                $cloneUpgrade = $input->getArgument('mode') == 'upgrade';
            }
        }

        if ($cloneUpgrade && ($onlyData || $onlyCode)) {
            $this->io->error('The options --only-code and --only-data cannot be used when cloning and upgrading an instance.');
            return 1;
        }

        if ($clone != false || $cloneUpgrade != false) {
            $offset = 1;
        }

        $arguments = array_slice($input->getArgument('mode'), $offset);
        if (!empty($arguments[0])) {
            $sourceInstances = getEntries($instances, $arguments[0]);
        } elseif ($sourceOption = $input->getOption("source")) {
            $sourceInstances = CommandHelper::validateInstanceSelection($sourceOption, $instances);
        } else {
            $this->io->newLine();
            $output->writeln('<comment>NOTE: Clone operations are only available on Local and SSH instances.</comment>');

            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $question = CommandHelper::getQuestion('Select the source instance', null);
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $sourceInstances = $helper->ask($input, $output, $question);
        }

        $sourceInstance = $sourceInstances[0];
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
            $targetInstances = getEntries($instances, $arguments[1]);
        } elseif ($targetOption = implode(',', $input->getOption('target'))) {
            $targetInstances = CommandHelper::validateInstanceSelection($targetOption, $instances);
        } else {
            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $question = CommandHelper::getQuestion('Select the destination instance(s)', null);
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $targetInstances = $helper->ask($input, $output, $question);
        }

        if ($setupTargetDatabase && count($targetInstances) > 1) {
            $this->io->error('Database setup options can only be used when a single target instance is passed.');
            return 1;
        }

        if ($cloneUpgrade) {
            $branch = $arguments[2] ?? $input->getOption('branch');
            $ignoreReq = $input->getOption('ignore-requirements') ?? false;

            // Get current version from Source
            $curVersion = $sourceInstance->getLatestVersion();

            $upVersion = null;

            foreach ($targetInstances as $key => $targetInstance) {
                $targetInstance->detectPHP();
                if (!$upVersion) {
                    $upVersion = $this->getUpgradeVersion($targetInstance, !$ignoreReq, $branch, $curVersion);
                    continue;
                }

                if (!$this->validateUpgradeVersion($targetInstance, !$ignoreReq, $upVersion->branch, $curVersion)) {
                    $this->io->writeln('Cannot clone&upgrade to %s, as version is not supported by server requirements.');
                    unset($targetInstances[$key]);
                }
            }

            $input->setOption('branch', $upVersion->branch);
        }

        // PRE-CHECK
        $this->io->newLine();
        $this->io->section('Pre-check');

        $directWarnMessage = 'Direct backup cannot be used, instance {instance_name} is ftp.';
        // Check if direct flag can be used
        if ($direct && $sourceInstance->type == 'ftp') {
            $direct = false;
            $this->logger->warning($directWarnMessage, ['instance_name' => $sourceInstance->name]);
        }

        if ($direct) {
            foreach ($targetInstances as $destinationInstance) {
                if ($destinationInstance->type == 'ssh' && $sourceInstance->type == 'ssh') {
                    $directWarnMessage = 'Direct backup cannot be used, instance {source_name} and instance {target_name} are both ssh.';
                    $this->logger->warning(
                        $directWarnMessage,
                        ['target_name' => $destinationInstance->name, 'source_name' => $sourceInstance->name]
                    );
                    $direct = false;
                    break;
                }

                if ($destinationInstance->type == 'ftp') {
                    $this->logger->warning($directWarnMessage, ['instance_name' => $destinationInstance->name]);
                    $direct = false;
                    break;
                }
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

        foreach ($targetInstances as $key => $destinationInstance) {
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
                unset($targetInstances[$key]);
                continue;
            }

            if ($this->isSameDatabase($sourceInstance, $destinationInstance) && ! $onlyCode) {
                $this->logger->error('Database host and name are the same in the source ({source_instance_name}) and destination ({target_instance_id}).', [
                    'source_instance_name' => $sourceInstance->name,
                    'target_instance_id' => $destinationInstance->name
                ]);
                unset($targetInstances[$key]);
                continue;
            }
        }

        if (empty($targetInstances)) {
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
                        return 1;
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
            return 1;
        }

        $options = [
            'checksum-check' => $checksumCheck,
            'skip-reindex' => $skipReindex,
            'skip-cache-warmup' => $skipCache,
            'live-reindex' => $liveReindex,
            'timeout' => $timeout,
        ];

        /** @var Instance $destinationInstance */
        foreach ($targetInstances as $destinationInstance) {
            $this->io->newLine();
            $this->io->section('Initiating clone of ' . $sourceInstance->name . ' to ' . $destinationInstance->name);

            $instanceVCS = $destinationInstance->getVersionControlSystem();
            $instanceVCS->setLogger($this->logger);
            $instanceVCS->setVCSOptions($vcsOptions);

            $destinationInstance->lock();
            $errors = $destinationInstance->restore($sourceInstance, $archive, true, $checksumCheck, $direct, $onlyData, $onlyCode, $options);

            if (isset($errors)) {
                return 1;
            }

            if ($cloneUpgrade) {
                $branch = $input->getOption('branch');
                $branch = VersionControl::formatBranch($branch, $destinationInstance->vcs_type);
                $upgrade_version = Version::buildFake($destinationInstance->vcs_type, $branch);

                $output->writeln('<fg=cyan>Upgrading to version ' . $upgrade_version->branch . '</>');
                $app = $destinationInstance->getApplication();

                try {
                    $app->performUpgrade($destinationInstance, $upgrade_version, $options);
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
