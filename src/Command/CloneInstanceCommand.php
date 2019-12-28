<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Libs\Database\Database;
use TikiManager\Libs\Helpers\VersionControl;

class CloneInstanceCommand extends Command
{
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
                'Skip rebuilding index step.'
            )
            ->addOption(
                'skip-cache-warmup',
                null,
                InputOption::VALUE_NONE,
                'Skip generating cache step.'
            )
            ->addOption(
                'live-reindex',
                null,
                InputOption::VALUE_NONE,
                'Live reindex, set instance maintenance off and after perform index rebuild.'
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $io = new SymfonyStyle($input, $output);
            $helper = $this->getHelper('question');

            $clone = false;
            $cloneUpgrade = false;
            $offset = 0;

            $checksumCheck = $input->getOption('check');
            $skipReindex = $input->getOption('skip-reindex');
            $skipCache = $input->getOption('skip-cache-warmup');
            $liveReindex = $input->getOption('live-reindex');
            $direct = $input->getOption('direct');
            $keepBackup = $input->getOption('keep-backup');
            $useLastBackup = $input->getOption('use-last-backup');
            $argument = $input->getArgument('mode');

            if ($direct && ($keepBackup || $useLastBackup)) {
                $io->error('The options --direct and --keep-backup or --use-last-backup could not be used in conjunction, instance filesystem is not in the backup file.');
                exit(-1);
            }

            if (isset($argument) && !empty($argument)) {
                if (is_array($argument)) {
                    $clone = $input->getArgument('mode')[0] == 'clone' ? true : false;
                    $cloneUpgrade = $input->getArgument('mode')[0] == 'upgrade' ? true : false;
                } else {
                    $cloneUpgrade = $input->getArgument('mode') == 'upgrade' ? true : false;
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
                $io->newLine();
                $output->writeln('<comment>NOTE: Clone operations are only available on Local and SSH instances.</comment>');

                $io->newLine();
                $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

                $question = CommandHelper::getQuestion('Select the source instance', null);
                $question->setValidator(function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                });

                $selectedSourceInstances = $helper->ask($input, $output, $question);
            }

            $instances_pruned = [];
            $instances = CommandHelper::getInstances('all');
            foreach ($instances as $instance) {
                if ($instance->getId() == $selectedSourceInstances[0]->getId()) {
                    continue;
                }
                $instances_pruned[$instance->getId()] = $instance;
            }
            $instances = $instances_pruned;

            $databaseConfig = CommandHelper::setupDatabaseConnection($selectedSourceInstances[0], $input, $output);
            $selectedSourceInstances[0]->setDatabaseConfig($databaseConfig);

            $instancesInfo = CommandHelper::getInstancesInfo($instances);
            if (isset($instancesInfo)) {
                if (!empty($arguments[1])) {
                    $selectedDestinationInstances = getEntries($instances, $arguments[1]);
                } elseif ($targetOption = implode(',', $input->getOption("target"))) {
                    $selectedDestinationInstances = CommandHelper::validateInstanceSelection($targetOption, $instances);
                } else {
                    $io->newLine();
                    $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

                    $question = CommandHelper::getQuestion('Select the destination instance(s)', null);
                    $question->setValidator(function ($answer) use ($instances) {
                        return CommandHelper::validateInstanceSelection($answer, $instances);
                    });

                    $selectedDestinationInstances = $helper->ask($input, $output, $question);
                }

                if ($cloneUpgrade) {
                    if (!empty($arguments[2])) {
                        $input->setOption('branch', $arguments[2]);
                    } else {
                        $branch = $input->getOption('branch');
                        if (empty($branch)) {
                            $branch = $this->getUpgradeVersion($selectedSourceInstances[0], $input, $output);
                            $input->setOption('branch', $branch);
                        }
                    }
                }

                foreach ($selectedDestinationInstances as $destinationInstance) {
                    $destinationInstance->app = $selectedSourceInstances[0]->app; // Required to setup database connection
                    $databaseConfig = CommandHelper::setupDatabaseConnection($destinationInstance, $input, $output);
                    $destinationInstance->setDatabaseConfig($databaseConfig);

                    if ($direct && ($selectedSourceInstances[0]->type != 'local' || $destinationInstance->type != 'local')) {
                        $output->writeln('<comment>Direct backup cannot be used, instance "' . $selectedSourceInstances[0]->name . '" and "' . $destinationInstance->name . '" are not in same location.</comment>');
                        $direct = false;
                    }
                }

                $archive = '';
                $standardProcess = true;
                if ($useLastBackup) {
                    $standardProcess = false;
                    $archiveDir = rtrim($_ENV['ARCHIVE_FOLDER'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                    $archiveDir .= sprintf('%s-%s', $selectedSourceInstances[0]->id, $selectedSourceInstances[0]->name);

                    if (file_exists($archiveDir)) {
                        $archiveFiles = array_diff(scandir($archiveDir, SCANDIR_SORT_DESCENDING), ['.', '..']);
                        if (! empty($archiveFiles[0])) {
                            $archive = $archiveDir . DIRECTORY_SEPARATOR . $archiveFiles[0];
                            $output->writeln('<fg=cyan>Using last created backup of: ' . $selectedSourceInstances[0]->name . '</>');
                            $keepBackup = true;
                        } else {
                            $standardProcess = $io->confirm('Backups not found for ' . $selectedSourceInstances[0]->name . ' instance. Continue with standard process?', true);

                            if (!$standardProcess) {
                                $io->error('Backups not found for instance ' . $selectedSourceInstances[0]->name);
                                exit(-1);
                            }
                        }
                    }
                }

                if ($standardProcess) {
                    $output->writeln('<fg=cyan>Creating snapshot of: ' . $selectedSourceInstances[0]->name . '</>');
                    $archive = $selectedSourceInstances[0]->backup();
                }

                if (empty($archive)) {
                    $output->writeln('<error>Error: Snapshot creation failed.</error>');
                    exit(-1);
                }

                $sourceAccess = $selectedSourceInstances[0]->getBestAccess('scripting');
                $sourceDB = $selectedSourceInstances[0]->getDatabaseConfig();

                /** @var Instance $destinationInstance */
                foreach ($selectedDestinationInstances as $destinationInstance) {
                    $targetAccess = $destinationInstance->getBestAccess('scripting');
                    $targetDB = $destinationInstance->getDatabaseConfig();
                    if (($sourceAccess->host == $targetAccess->host ||
                            ($sourceAccess->host != $targetAccess->host && !in_array($targetDB->host, ['127.0.0.1', 'localhost'])))
                        && Database::compareDatabase($sourceDB, $targetDB)) {
                        $output->writeln('<error>Database host and name are the same in the source (' . $selectedSourceInstances[0]->id . ') and destination (' . $destinationInstance->id . ').</error>');
                        continue;
                    }

                    $output->writeln('<fg=cyan>Initiating clone of ' . $selectedSourceInstances[0]->name . ' to ' . $destinationInstance->name . '</>');

                    $destinationInstance->lock();
                    $destinationInstance->restore($selectedSourceInstances[0], $archive, true, false, $direct);

                    if ($cloneUpgrade) {
                        $branch = $input->getOption('branch');
                        $upgrade_version = Version::buildFake($destinationInstance->vcs_type, VersionControl::formatBranch($branch));

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
                            CommandHelper::setInstanceSetupError($destinationInstance->id, $input, $output);
                            exit(-1);
                        }
                    }
                    if ($destinationInstance->isLocked()) {
                        $destinationInstance->unlock();
                    }
                }

                if (! $keepBackup) {
                    $output->writeln('<fg=cyan>Deleting archive...</>');
                    $access = $selectedSourceInstances[0]->getBestAccess('scripting');
                    $access->shellExec("rm -f " . $archive);
                }
            } else {
                $output->writeln('<comment>No instances available as destination.</comment>');
            }
        } else {
            $output->writeln('<comment>No instances available to clone/clone and upgrade.</comment>');
        }
    }

    /**
     * Get version to update instance to
     *
     * @param Instance $instance
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return string
     */
    private function getUpgradeVersion($instance, $input, $output)
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

        $output->writeln('<fg=cyan>We detected PHP release: ' . CommandHelper::formatPhpVersion($instance->phpversion) . '</>');

        if ($found_incompatibilities) {
            $output->writeln('<comment>If some versions are not offered, it\'s likely because the host</comment>');
            $output->writeln('<comment>server doesn\'t meet the requirements for that version (ex: PHP version is too old)</comment>');
        }

        $io = new SymfonyStyle($input, $output);
        $choice = $io->choice('Which version do you want to update to', $choices);
        $choice = explode(':', $choice);
        return trim($choice[1]);
    }
}
