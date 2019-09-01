<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use TikiManager\Application\Discovery;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Libs\Helpers\Checksum;

class UpdateInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:update')
            ->setDescription('Update instance')
            ->setHelp('This command allows you update an instance')
            ->addArgument('mode', InputArgument::IS_ARRAY | InputArgument::OPTIONAL)
            ->addOption(
                'instances',
                null,
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
                null,
                InputOption::VALUE_REQUIRED,
                'Email address to notify in case of failure. Use , (comma) to separate multiple email addresses.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('update');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        $io = new SymfonyStyle($input, $output);

        if (isset($instancesInfo)) {
            $helper = $this->getHelper('question');
            $instancesOption = $input->getOption('instances');

            $auto = false;
            $switch = false;

            $argument = $input->getArgument('mode');
            if (isset($argument) && !empty($argument)) {
                if (is_array($argument)) {
                    $auto = $input->getArgument('mode')[0] == 'auto' ? true : false;
                    $switch = $input->getArgument('mode')[0] == 'switch' ? true : false;
                } else {
                    $switch = $input->getArgument('mode') == 'switch' ? true : false;
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
                    $io->newLine();
                    CommandHelper::renderInstancesTable($output, $instancesInfo);

                    $io->newLine();
                    $io->writeln('<comment>In case you want to ' . $action . ' more than one instance, please use a comma (,) between the values</comment>');

                    $question = CommandHelper::getQuestion('Which instance(s) do you want to ' . $action, null, '?');
                    $question->setValidator(function ($answer) use ($instances) {
                        return CommandHelper::validateInstanceSelection($answer, $instances);
                    });

                    $selectedInstances = $helper->ask($input, $output, $question);
                } else {
                    CommandHelper::validateInstanceSelection($instancesOption, $instances);
                    $instancesOption = explode(',', $instancesOption);
                    $selectedInstances = array_intersect_key($instances, array_flip($instancesOption));
                }
            }

            $checksumCheck = $input->getOption('check');

            $logs = [];
            foreach ($selectedInstances as $instance) {
                $log = [];
                $log[] = sprintf('## %s (id: %s)' . PHP_EOL, $instance->name, $instance->id);

                $access = $instance->getBestAccess('scripting');
                $discovery = new Discovery($instance, $access);
                $phpVersion = $discovery->detectPHPVersion();
                if (preg_match('/(\d+)(\d{2})(\d{2})$/', $phpVersion, $matches)) {
                    $phpVersion = sprintf("%d.%d.%d", $matches[1], $matches[2], $matches[3]);
                }

                $io->writeln('<fg=cyan>Working on ' . $instance->name . "\nPHP version $phpVersion found at " . $discovery->detectPHP() . '</>');

                $locked = $instance->lock();
                $instance->detectPHP();
                $app = $instance->getApplication();
                $version = $instance->getLatestVersion();
                $branch_name = $version->getBranch();
                $branch_version = $version->getBaseVersion();

                if ($switch) {
                    $versionSel = [];
                    $branch = $input->getOption('branch');
                    $versions = [];
                    $versions_raw = $app->getVersions();
                    foreach ($versions_raw as $version) {
                        if ($version->type == 'svn' || $version->type == 'git') {
                            $versions[] = $version;
                        }
                    }

                    $io->writeln('<fg=cyan>You are currently running: ' . $branch_name . '</>');

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
                                if ($locked) {
                                    $instance->unlock();
                                }
                                return;
                            }
                            $versionSel = getEntries($versions, $selectedVersion);
                        } else {
                            $question = CommandHelper::getQuestion('Which version do you want to upgrade to', null, '?');
                            $selectedVersion = $helper->ask($input, $output, $question);
                            $versionSel = getEntries($versions, $selectedVersion);
                        }

                        if (empty($versionSel) && !empty($selectedVersion)) {
                            $target = Version::buildFake('svn', $selectedVersion);
                        } else {
                            $target = reset($versionSel);
                        }

                        if (count($versionSel) > 0) {
                            $filesToResolve = $app->performUpdate($instance, $target, $checksumCheck);
                            $version = $instance->getLatestVersion();

                            if ($checksumCheck) {
                                Checksum::handleCheckResult($instance, $version, $filesToResolve, $io);
                            }
                        } else {
                            $io->writeln('<comment>No version selected. Nothing to perform.</comment>');
                        }
                    } else {
                        $io->writeln('<comment>No upgrades are available. This is likely because you are already at</comment>');
                        $io->writeln('<comment>the latest version permitted by the server.</comment>');
                    }
                } else {
                    $app_branch = $app->getBranch();
                    if ($app_branch == $branch_name) {
                        try {
                            $filesToResolve = $app->performUpdate($instance, null, $checksumCheck);
                            $version = $instance->getLatestVersion();

                            if ($checksumCheck) {
                                Checksum::handleCheckResult($instance, $version, $filesToResolve, $io);
                            }
                        } catch (\Exception $e) {
                            $log[] = $e->getMessage() . PHP_EOL;
                            $log[] = $e->getTraceAsString() . PHP_EOL;
                        }
                    } else {
                        $message = 'Tiki Application branch is different than the one stored in the Tiki Manager db.';
                        $log[] = $message;
                        $io->error($message);
                    }
                }

                if (count($log) > 1) {
                    $logs = array_merge($logs, $log);
                }

                if ($locked) {
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
                    CommandHelper::sendMailNotification(
                        $emails,
                        '[Tiki-Manager] ' . $this->getName() . ' report failures',
                        $logs
                    );
                } catch (\RuntimeException $e) {
                    debug($e->getMessage());
                    $io->error($e->getMessage());
                }
            }

            if (!empty($logs)) {
                return 1;
            }
        } else {
            $io->writeln('<comment>No instances available to update/upgrade.</comment>');
        }
    }
}
