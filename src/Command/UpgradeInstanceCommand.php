<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Application\Discovery;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Libs\Helpers\Checksum;

class UpgradeInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:upgrade')
            ->setDescription('Upgrade instance')
            ->setHelp('This command allows you to upgrade an instance')
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Skip files checksum check for a faster result. Files checksum change won\'t be saved on the DB.'
            )
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be updated, separated by comma (,)'
            )
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Instance branch to update'
            );
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $checksumCheck = false;
        if ($input->getOption('check')) {
            $checksumCheck = true;
        }
        $instancesOption = $input->getOption('instances');
        $instances = CommandHelper::getInstances('update');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesOption)) {
            $io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $io->newLine();
            $io->writeln('<comment>In case you want to upgrade more than one instance, please use a comma (,) between the values</comment>');

            $question = CommandHelper::getQuestion('Which instance(s) do you want to upgrade', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
        } else {
            $validInstances = CommandHelper::validateInstanceSelection($instancesOption, $instances);
            $instancesOption = explode(',', $instancesOption);
            $selectedInstances = array_intersect_key($instances, array_flip($instancesOption));
        }


        //update
        foreach ($selectedInstances as $instance) {
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
                if (!empty($branch)) {
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


            if ($locked) {
                $instance->unlock();
            }
        }
    }
}
