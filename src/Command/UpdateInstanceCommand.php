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
                'skip-checksum',
                null,
                InputOption::VALUE_NONE,
                'Skip files checksum check for a faster result. Files checksum change won\'t be saved on the DB.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('update');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $helper = $this->getHelper('question');

            $auto = false;
            $switch = false;
            $offset = 0;

            $argument = $input->getArgument('mode');
            if (isset($argument) && ! empty($argument)) {
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

                $io = new SymfonyStyle($input, $output);
                $output->writeln('<comment>WARNING: Only SVN instances can be ' . $action . 'd.</comment>');

                $io->newLine();
                $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

                $io->newLine();
                $output->writeln('<comment>In case you want to ' . $action .' more than one instance, please use a comma (,) between the values</comment>');

                $question = CommandHelper::getQuestion('Which instance(s) do you want to ' . $action, null, '?');
                $question->setValidator(function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                });

                $selectedInstances = $helper->ask($input, $output, $question);
            }

            $skipChecksum = $input->getOption('skip-checksum');

            foreach ($selectedInstances as $instance) {
                $access = $instance->getBestAccess('scripting');
                $discovery = new Discovery($instance, $access);
                $php_version_output = $discovery->detectPHPVersion();

                if (preg_match('/(\d+)(\d{2})(\d{2})$/', $php_version_output, $matches)) {
                    $php_version_output = "$matches[1].$matches[2].$matches[3]";
                }

                $output->writeln('<fg=cyan>Working on ' . $instance->name . "\nPHP version $php_version_output found at " . $discovery->detectPHP() .'</>');

                $locked = $instance->lock();
                $instance->detectPHP();
                $app = $instance->getApplication();

                if (!$app->isInstalled()) {
                    ob_start();
                    perform_instance_installation($instance);
                    $contents = $string = trim(preg_replace('/\s\s+/', ' ', ob_get_contents()));
                    ob_end_clean();

                    $matches = [];
                    if (preg_match('/(\d+\.|trunk)/', $contents, $matches)) {
                        $branch_name = $matches[0];
                    }
                }

                $version = $instance->getLatestVersion();
                $branch_name = $version->getBranch();
                $branch_version = $version->getBaseVersion();
                $skip_checksum = $input->getOption('skip-checksum');

                if ($switch) {
                    $versions = [];
                    $versions_raw = $app->getVersions();
                    foreach ($versions_raw as $version) {
                        if ($version->type == 'svn') {
                            $versions[] = $version;
                        }
                    }

                    $output->writeln('<fg=cyan>You are currently running: ' . $branch_name . '</>');

                    $counter = 0;
                    $found_incompatibilities = false;
                    foreach ($versions as $key => $version) {
                        $base_version = $version->getBaseVersion();

                        $compatible = 0;
                        $compatible |= $base_version >= 13;
                        $compatible &= $base_version >= $branch_version;
                        $compatible |= $base_version === 'trunk';
                        $compatible &= $instance->phpversion > 50500;
                        $found_incompatibilities |= !$compatible;

                        if ($compatible) {
                            $counter++;
                            $output->writeln('[' . $key .'] ' . $version->type . ' : ' . $version->branch);
                        }
                    }

                    if ($counter) {
                        $question = CommandHelper::getQuestion('Which version do you want to upgrade to', null, '?');
                        $selectedVersion = $helper->ask($input, $output, $question);
                        $versionSel = getEntries($versions, $selectedVersion);

                        if (empty($versionSel) && ! empty($selectedVersion)) {
                            $target = Version::buildFake('svn', $selectedVersion);
                        } else {
                            $target = reset($versionSel);
                        }

                        if (count($versionSel) > 0) {
                            $filesToResolve = $app->performUpdate($instance, $target, $skipChecksum);
                            $version = $instance->getLatestVersion();
                            if (!$skipChecksum) {
                                handleCheckResult($instance, $version, $filesToResolve);
                            }
                        } else {
                            $output->writeln('<comment>No version selected. Nothing to perform.</comment>');
                        }
                    } else {
                        $output->writeln('<comment>No upgrades are available. This is likely because you are already at</comment>');
                        $output->writeln('<comment>the latest version permitted by the server.</comment>');
                    }
                } else {
                    $app_branch = $app->getBranch();
                    if ($app_branch == $branch_name) {
                        $filesToResolve = $app->performUpdate($instance, null, $skipChecksum);
                        $version = $instance->getLatestVersion();
                        if (!$skipChecksum) {
                            handleCheckResult($instance, $version, $filesToResolve);
                        }
                    } else {
                        $output->writeln('<error>Error: Tiki Application branch is different than the one stored in the Tiki Manager db.</error>');
                        exit(-1);
                    }
                }

                if ($locked) {
                    $instance->unlock();
                }
            }
        } else {
            $output->writeln('<comment>No instances available to update/upgrade.</comment>');
        }
    }
}
