<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\SendEmail;
use TikiManager\Libs\Helpers\Checksum;
use TikiManager\Logger\ArrayHandler;

class UpdateInstanceCommand extends TikiManagerCommand
{
    use SendEmail;

    protected function configure()
    {
        $this
            ->setName('instance:update')
            ->setDescription('Update instance')
            ->setHelp('This command allows you update an instance')
            ->addArgument('mode', InputArgument::IS_ARRAY | InputArgument::OPTIONAL)
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_OPTIONAL,
                'List of instance IDs to be updated, separated by comma (,)'
            )
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Instance branch to update'
            )
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check files checksum after operation has been performed.'
            )
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Email address to notify in case of failure. Use , (comma) to separate multiple email addresses.'
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
                InputOption::VALUE_OPTIONAL,
                'Live reindex, set instance maintenance off and after perform index rebuild.',
                true
            )
            ->addOption(
                'lag',
                null,
                InputOption::VALUE_REQUIRED,
                'Time delay commits by X number of days. Useful for avoiding newly introduced bugs in automated updates.'
            )
        ->addOption(
            'stash',
            null,
            InputOption::VALUE_NONE,
            'Only on Git: saves your local modifications, and try to apply after update/upgrade'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('update');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (isset($instancesInfo)) {
            $instancesOption = $input->getOption('instances');

            $auto = false;
            $switch = false;

            $argument = $input->getArgument('mode');
            if (isset($argument) && !empty($argument)) {
                if (is_array($argument)) {
                    $auto = $input->getArgument('mode')[0] == 'auto';
                    $switch = $input->getArgument('mode')[0] == 'switch';
                } else {
                    $switch = $input->getArgument('mode') == 'switch';
                }
            }

            if ($auto) {
                $instancesIds = array_slice($input->getArgument('mode'), 1);

                $selectedInstances = [];
                foreach ($instancesIds as $index) {
                    if (array_key_exists($index, $instances)) {
                        $selectedInstances[] = $instances[$index];
                    }
                }
            } else {
                $action = 'update';
                if ($switch) {
                    $action = 'upgrade';
                }

                if (empty($instancesOption)) {
                    $this->io->newLine();
                    CommandHelper::renderInstancesTable($output, $instancesInfo);

                    $this->io->newLine();
                    $this->io->writeln('<comment>In case you want to ' . $action . ' more than one instance, please use a comma (,) between the values</comment>');

                    $selectedInstances = $this->io->ask(
                        'Which instance(s) do you want to ' . $action . '?',
                        null,
                        function ($answer) use ($instances) {
                            return CommandHelper::validateInstanceSelection($answer, $instances);
                        }
                    );
                } else {
                    CommandHelper::validateInstanceSelection($instancesOption, $instances);
                    $instancesOption = explode(',', $instancesOption);
                    $selectedInstances = array_intersect_key($instances, array_flip($instancesOption));
                }
            }

            $lag = $input->getOption('lag');
            if ($lag && (!is_numeric($lag) || $lag < 0)) {
                $this->io->error('Invalid option for --lag, must be a positive integer.');
                return 1;
            }

            $checksumCheck = $input->getOption('check');
            $skipReindex = $input->getOption('skip-reindex');
            $skipCache = $input->getOption('skip-cache-warmup');
            $liveReindex = filter_var($input->getOption('live-reindex'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            $logs = [];

            $vcsOptions = [
                'allow_stash' => $input->getOption('stash')
            ];

            /** @var Instance $instance */
            foreach ($selectedInstances as $instance) {
                $instanceLogger = $this->logger->withName('instance_' . $instance->id);
                $arrHandler = new ArrayHandler(Logger::ERROR);
                $arrHandler->setFormatter($this->getFormatter());
                $instanceLogger->pushHandler($arrHandler);

                $instanceVCS = $instance->getVersionControlSystem();
                $instanceVCS->setLogger($instanceLogger);
                $instanceVCS->setVCSOptions($vcsOptions);

                // Ensure that the current phpexec is still valid;
                $instance->detectPHP();
                $phpVersion = CommandHelper::formatPhpVersion($instance->phpversion);

                $message = sprintf(
                    "Working on %s\nPHP version %s found at %s.",
                    $instance->name,
                    $phpVersion,
                    $instance->phpexec
                );
                $this->io->writeln('<fg=cyan>' .$message. '</>');

                $instance->lock();
                $app = $instance->getApplication();
                $version = $instance->getLatestVersion();
                $branch_name = $version->getBranch();
                $branch_version = $version->getBaseVersion();

                if ($switch) {
                    $versionSel = [];
                    $branch = $input->getOption('branch');
                    $versions = $app->getCompatibleVersions(false);

                    $this->io->writeln('<fg=cyan>You are currently running: ' . $branch_name . '</>');

                    $counter = 0;
                    $found_incompatibilities = false;
                    foreach ($versions as $key => $version) {
                        $base_version = $version->getBaseVersion();

                        $compatible = 0;
                        $compatible |= $base_version >= 13;
                        $compatible &= $base_version >= $branch_version;
                        $compatible |= $base_version === 'trunk';
                        $compatible |= $base_version === 'master';
                        $compatible &= $instance->phpversion > 50500;
                        $found_incompatibilities |= !$compatible;

                        if ($compatible) {
                            $counter++;
                            if (empty($branch)) {
                                $output->writeln('[' . $key . '] ' . $version->type . ' : ' . $version->branch);
                            } elseif (($branch == $version->getBranch()) || ($branch === $base_version)) {
                                $branch = $key;
                            }
                        }
                    }

                    if ($counter) {
                        if (! empty($branch)) {
                            $selectedVersion = $branch;
                            if (!array_key_exists($selectedVersion, $versions)) {
                                $output->writeln('Branch ' . $input->getOption('branch') . ' not found');
                                if ($instance->isLocked()) {
                                    $instance->unlock();
                                }
                                continue;
                            }
                            $versionSel = getEntries($versions, $selectedVersion);
                        } else {
                            $selectedVersion = $this->io->ask('Which version do you want to upgrade to?', null);
                            $versionSel = getEntries($versions, $selectedVersion);
                        }

                        if (empty($versionSel) && !empty($selectedVersion)) {
                            $target = Version::buildFake('svn', $selectedVersion);
                        } else {
                            $target = reset($versionSel);
                        }

                        if (count($versionSel) > 0) {
                            try {
                                $filesToResolve = $app->performUpdate($instance, $target, [
                                    'checksum-check' => $checksumCheck,
                                    'skip-reindex' => $skipReindex,
                                    'skip-cache-warmup' => $skipCache,
                                    'live-reindex' => $liveReindex
                                ]);
                                $version = $instance->getLatestVersion();

                                if ($checksumCheck) {
                                    Checksum::handleCheckResult($instance, $version, $filesToResolve);
                                }
                            } catch (\Exception $e) {
                                CommandHelper::setInstanceSetupError($instance->id, $e);
                            }
                        } else {
                            $this->io->writeln('<comment>No version selected. Nothing to perform.</comment>');
                        }
                    } else {
                        $this->io->writeln('<comment>No upgrades are available. This is likely because you are already at</comment>');
                        $this->io->writeln('<comment>the latest version permitted by the server.</comment>');
                    }
                } else {
                    $app_branch = $app->getBranch();
                    if ($app_branch == $branch_name) {
                        try {
                            $filesToResolve = $app->performUpdate($instance, null, [
                                'checksum-check' => $checksumCheck,
                                'skip-reindex' => $skipReindex,
                                'skip-cache-warmup' => $skipCache,
                                'live-reindex' => $liveReindex,
                                'lag' => $lag
                            ]);
                            $version = $instance->getLatestVersion();

                            if ($checksumCheck) {
                                Checksum::handleCheckResult($instance, $version, $filesToResolve);
                            }
                        } catch (\Exception $e) {
                            $instanceLogger->error('Failed to update instance!', [
                                'instance' => $instance->name,
                                'exception' => $e,
                            ]);
                            CommandHelper::setInstanceSetupError($instance->id, $e);
                        }
                    } else {
                        $message = 'Tiki Application branch is different than the one stored in the Tiki Manager db.';
                        $instanceLogger->error($message);
                    }
                }

                if ($instanceLogs = $arrHandler->getLog()) {
                    $logs[] = sprintf('<h1>%s (id: %s)</h1>', $instance->name, $instance->id);
                    $logs[] = '<pre>' . implode("\n", $instanceLogs) . '</pre>';
                }

                if ($instance->isLocked()) {
                    $instance->unlock();
                }
            }

            $emails = $input->getOption('email');
            $emails = array_filter(explode(',', $emails), function ($email) {
                return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
            });

            if (!empty($logs) && !empty($emails)) {
                $logs = implode(PHP_EOL, $logs);
                try {
                    $this->sendEmail(
                        $emails,
                        '[Tiki-Manager] ' . $this->getName() . ' report failures',
                        $logs
                    );
                } catch (\RuntimeException $e) {
                    debug($e->getMessage());
                    $this->io->error($e->getMessage());
                }
            }

            if (!empty($logs)) {
                return 1;
            }
        } else {
            $this->io->writeln('<comment>No instances available to update/upgrade.</comment>');
        }
    }

    private function getFormatter()
    {
        $formatter = new LineFormatter();
        $formatter->ignoreEmptyContextAndExtra(true);
        $formatter->allowInlineLineBreaks(true);
        $formatter->includeStacktraces(true);

        return $formatter;
    }
}
