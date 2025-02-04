<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Application\Instance;
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

    protected $mode = 'clone';
    protected $revision = '';
    protected $repoURL;

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    public function setRevision($revision)
    {
        $this->revision = $revision;
    }

    public function setRepoUrl($repo)
    {
        $this->repoURL = $repo;
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:clone')
            ->setDescription('Clone instance')
            ->setHelp('This command allows you make another identical copy of Tiki')
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
                'Saves your local modifications, and try to apply after update/upgrade'
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
            )
            ->addOption(
                'skip-config-check',
                null,
                InputOption::VALUE_NONE,
                'Skip system_configuration_file check.'
            )
            ->addOption(
                'allow-common-parent-levels',
                null,
                InputOption::VALUE_REQUIRED,
                'Allow files and folders to be restored if they share the n-th parent use 0 (default) for the instance root folder and N (>=1) for allowing parent folders. Use -1 to skip this check',
                "0"
            )
            ->addOption(
                'skip-lock',
                null,
                InputOption::VALUE_NONE,
                'Skip lock website.'
            )
            ->addOption(
                'skip-index-backup',
                null,
                InputOption::VALUE_NONE,
                'Skip the index table'
            )
            ->addOption(
                'copy-errors',
                null,
                InputOption::VALUE_OPTIONAL,
                'Handle rsync errors: use "stop" to halt on errors or "ignore" to proceed despite errors'
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
        $revision = $this->revision;
        $cloneUpgrade = $this->mode === 'upgrade';
        $checksumCheck = $input->getOption('check');
        $skipReindex = $input->getOption('skip-reindex');
        $skipCache = $input->getOption('skip-cache-warmup');
        $liveReindex = is_null($input->getOption('live-reindex')) ? true : filter_var($input->getOption('live-reindex'), FILTER_VALIDATE_BOOLEAN);
        $direct = $input->getOption('direct');
        $keepBackup = $input->getOption('keep-backup');
        $useLastBackup = $input->getOption('use-last-backup');
        $sourceOption = $input->getOption("source");
        $targetOption = implode(",", $input->getOption('target'));
        $onlyData = $input->getOption('only-data');
        $onlyCode = $input->getOption('only-code');
        $skipLock = $input->getOption('skip-lock');
        $isIncludeIndex = $input->getOption('skip-index-backup') ? false : true;
        $vcsOptions = [
            'allow_stash' => $input->getOption('stash')
        ];
        $timeout = $input->getOption('timeout') ?: Environment::get('COMMAND_EXECUTION_TIMEOUT');
        putenv("COMMAND_EXECUTION_TIMEOUT=$timeout");

        $skipSystemConfigurationCheck = $input->getOption('skip-config-check') !== false;
        $allowCommonParents = (int)$input->getOption('allow-common-parent-levels');

        $setupTargetDatabase = (bool) ($input->getOption('db-prefix') || $input->getOption('db-name'));

        if ($direct && ($keepBackup || $useLastBackup)) {
            $this->io->error('The options --direct and --keep-backup or --use-last-backup could not be used in conjunction, instance filesystem is not in the backup file.');
            return 1;
        }

        if ($cloneUpgrade && ($onlyData || $onlyCode)) {
            $this->io->error('The options --only-code and --only-data cannot be used when cloning and upgrading an instance.');
            return 1;
        }

        if ($sourceOption) {
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

        $repoURL = $this->repoURL ?? $sourceInstance->repo_url;
        $sourceInstance->copy_errors = $input->getOption('copy-errors') ?: 'ask';

        $isBisectSession = $sourceInstance->getOnGoingBisectSession();
        if ($isBisectSession) {
            $actionMsg = $cloneUpgrade ? 'clone and upgrade' : 'clone';
            $this->io->warning($actionMsg . ' is skipped for Instance (' . $sourceInstance->id . ') because bisect session is ongoing for source instance');
            return 1;
        }

        if ($cloneUpgrade) {
            $instances = CommandHelper::getInstances('upgrade');
        } else {
            $instances = CommandHelper::getInstances('all');
        }

        $instances = array_filter($instances, function ($instance) use ($sourceInstance) {
            return $instance->getId() != $sourceInstance->getId();
        });

        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available as destination.</comment>');
            return 0;
        }

        if ($targetOption) {
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

        $inputBranchExist = true;
        $inputBranch = $input->getOption('branch')??'';
        if (trim($inputBranch) == '') {
            $inputBranchExist = false;
            $inputBranch = $sourceInstance->branch;
        }
        $bisectInstances = [];
        $protectedInstances = [];
        $actionMsg = $cloneUpgrade ? 'clone and upgrade' : 'clone';

        foreach ($targetInstances as $i => $targetInstance) {
            if ($targetInstance->isInstanceProtected()) {
                $protectedInstances[] = $targetInstance->name;
                unset($targetInstances[$i]);
                continue;
            }
            $isBisectSession = $targetInstance->getOnGoingBisectSession();
            if ($isBisectSession) {
                $bisectInstances[] = $targetInstance->id;
                unset($targetInstances[$i]);
            }
            // If we want clone data only, we need to use the branch from the destination instance
            if (!$inputBranchExist && $targetInstance->branch && $onlyData) {
                $inputBranch = $targetInstance->branch;
            }

            if ($targetInstance->validateBranchInRepo($inputBranch, $repoURL)) {
                $targetInstance->setBranchAndRepo($inputBranch, $repoURL);
            } else {
                $invalidVcsMsg = $actionMsg . ' is aborted because Instance (%s) branch (%s) does not belong to the repository (%s).';
                $invalidVcsMsg = sprintf($invalidVcsMsg, $targetInstance->id, $inputBranch, $repoURL);
                $this->io->error($invalidVcsMsg);
                unset($targetInstances[$i]);
            }
        }

        if (!empty($protectedInstances)) {
            $protectedMsg = $actionMsg . ' is skipped: target instance(s) are protected using the "sys_db_protected" tag: [%s].';
            $protectedMsg = sprintf($protectedMsg, implode(',', $protectedInstances));
            $this->io->warning($protectedMsg);
            $this->logger->warning($protectedMsg);
        }

        if (!empty($bisectInstances)) {
            $bisectMsg = $actionMsg . ' is skipped for Instance(s) [%s] because bisect session is ongoing for these target instances.';
            $bisectMsg = sprintf($bisectMsg, implode(',', $bisectInstances));
            $this->io->warning($bisectMsg);
            $this->logger->warning($bisectMsg);
        }

        if (empty($targetInstances)) {
            $noTargetInstancesMsg = 'No valid target instances to continue the clone process.';
            $this->logger->error($noTargetInstancesMsg);
            $this->io->error($noTargetInstancesMsg);
            return 1;
        }

        if ($setupTargetDatabase && count($targetInstances) > 1) {
            $this->logger->error('Database setup options can only be used when a single target instance is passed.');
            return 1;
        }

        if ($cloneUpgrade) {
            $branch = $input->getOption('branch');
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
        $this->io->writeln('Executing pre-check operations...');

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

        if (!$onlyCode) {
            $dbConfigErrorMessage = 'Unable to load/set database configuration for instance {instance_name} (id: {instance_id}). {exception_message}';

            try {
                // The source instance needs to be well configured by default
                if (!$this->testExistingDbConnection($sourceInstance)) {
                    throw new Exception('Existing database configuration failed to connect.');
                }
            } catch (Exception $e) {
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
                    $destinationInstance->copy_errors = $input->getOption('copy-errors') ?: 'ask';
                    if (! $setupTargetDatabase && ! $this->input->isInteractive() && ! $this->testExistingDbConnection($destinationInstance)) {
                        throw new Exception('Existing database configuration failed to connect.');
                    }

                    $this->setupDatabase($destinationInstance, $setupTargetDatabase);
                    $destinationInstance->database()->setupConnection();
                } catch (Exception $e) {
                    $this->logger->error($dbConfigErrorMessage, [
                        'instance_name' => $destinationInstance->name,
                        'instance_id' => $destinationInstance->getId(),
                        'exception_message' => $e->getMessage(),
                    ]);
                    unset($targetInstances[$key]);
                    continue;
                }

                if ($this->isSameDatabase($sourceInstance, $destinationInstance)) {
                    $this->logger->error('Database host and name are the same in the source ({source_instance_name}) and destination ({target_instance_id}).', [
                        'source_instance_name' => $sourceInstance->name,
                        'target_instance_id' => $destinationInstance->name
                    ]);
                    unset($targetInstances[$key]);
                    continue;
                }
            }
        }

        if (empty($targetInstances)) {
            $this->logger->error('No valid instances to continue the clone process.');
            return 1;
        }

        $hookName = $this->getCommandHook();
        $hookName->registerPostHookVars(['source' => $sourceInstance]);

        $archive = '';
        $standardProcess = true;
        if ($useLastBackup) {
            $standardProcess = false;
            $archiveDir = rtrim(getenv('ARCHIVE_FOLDER'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $archiveDir .= sprintf('%s-%s', $sourceInstance->id, $sourceInstance->name);

            if (file_exists($archiveDir)) {
                $archiveFiles = array_diff(scandir($archiveDir, SCANDIR_SORT_DESCENDING), ['.', '..']);
                if (!empty($archiveFiles[0])) {
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
                $archive = $sourceInstance->backup($direct, true, $onlyCode, $isIncludeIndex);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        if (empty($archive)) {
            $this->logger->error('Snapshot creation failed.');
            return 1;
        }

        $hookName->registerPostHookVars(['backup' => $archive]);

        $options = [
            'checksum-check' => $checksumCheck,
            'skip-reindex' => $skipReindex,
            'skip-cache-warmup' => $skipCache,
            'live-reindex' => $liveReindex,
            'timeout' => $timeout,
            'revision' => $revision
        ];

        /** @var Instance $destinationInstance */
        foreach ($targetInstances as $destinationInstance) {
            $this->io->newLine();
            $this->io->section('Initiating clone of ' . $sourceInstance->name . ' to ' . $destinationInstance->name);

            $destinationInstance->copy_errors = $input->getOption('copy-errors') ?: 'ask';
            $instanceVCS = $destinationInstance->getVersionControlSystem();
            $instanceVCS->setLogger($this->logger);
            $instanceVCS->setVCSOptions($vcsOptions);

            $hookName->registerPostHookVars(['instance' => $destinationInstance]);

            if (! $skipLock) {
                $destinationInstance->lock();
            }

            try {
                $errors = $destinationInstance->restore(
                    $sourceInstance,
                    $archive,
                    true,
                    $checksumCheck,
                    $direct,
                    $onlyData,
                    $onlyCode,
                    $options,
                    $skipSystemConfigurationCheck,
                    $allowCommonParents
                );
            } catch (\Throwable $e) {
                $errors = true;
                $this->io->error($e->getMessage());
            }

            if (isset($errors)) {
                $destinationInstance->updateState('failure', $this->getName(), 'clone function failure');
                $this->askUnlock($destinationInstance);
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
                } catch (Exception $e) {
                    $destinationInstance->updateState('failure', $this->getName(), 'performUpgrade function failure: ' . $e->getMessage());
                    CommandHelper::setInstanceSetupError($destinationInstance->id, $e, 'upgrade');
                    continue;
                }
            }

            if ($destinationInstance->isLocked()) {
                $destinationInstance->unlock();
            }

            $destinationInstance->updateState('success', $this->getName(), 'Instance cloned');
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

    /**
     * Ask to unlock the website
     *
     * @param Instance $instance
     * @return null
     */
    private function askUnlock(Instance $instance)
    {
        if ($instance->isLocked()) {
            $question = CommandHelper::getQuestion('Do you want to unlock the website? [y,n]');
            $question->setNormalizer(function ($value) {
                return (strtolower($value[0]) == 'y') ? true : false;
            });
            $confirm = $this->getHelper('question')->ask($this->input, $this->output, $question);
            if ($confirm) {
                $instance->unlock();
            }
        }
    }
}
