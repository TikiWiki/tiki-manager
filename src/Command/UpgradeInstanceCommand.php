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
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Libs\Helpers\Checksum;

class UpgradeInstanceCommand extends TikiManagerCommand
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
                'lag',
                null,
                InputOption::VALUE_REQUIRED,
                'Time delay commits by X number of days. Useful for avoiding newly introduced bugs in automated updates.'
            );
        ;
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $checksumCheck = false;
        if ($input->getOption('check')) {
            $checksumCheck = true;
        }
        $instancesOption = $input->getOption('instances');
        $instances = CommandHelper::getInstances('update');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        $skipReindex = $input->getOption('skip-reindex');
        $skipCache = $input->getOption('skip-cache-warmup');
        $liveReindex = $input->getOption('live-reindex');
        $lag = $input->getOption('lag');

        if (empty($instancesOption)) {
            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $this->io->newLine();
            $this->io->writeln('<comment>In case you want to upgrade more than one instance, please use a comma (,) between the values</comment>');

            $selectedInstances = $this->io->ask(
                'Which instance(s) do you want to upgrade?',
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


        //update
        foreach ($selectedInstances as $instance) {
            $discovery = $instance->getDiscovery();
            $phpVersion = CommandHelper::formatPhpVersion($discovery->detectPHPVersion());

            $this->io->writeln('<fg=cyan>Working on ' . $instance->name . "\nPHP version $phpVersion found at " . $discovery->detectPHP() . '</>');

            $instance->lock();
            $instance->detectPHP();
            $app = $instance->getApplication();
            $version = $instance->getLatestVersion();
            $branch_name = $version->getBranch();
            $branch_version = $version->getBaseVersion();

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
                if (!empty($branch)) {
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
                    $target = Version::buildFake($instance->vcs_type, $selectedVersion);
                } else {
                    $target = reset($versionSel);
                }

                if (count($versionSel) > 0) {
                    try {
                        $filesToResolve = $app->performUpgrade($instance, $target, [
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
                        CommandHelper::setInstanceSetupError($instance->id, $e);
                        continue;
                    }
                } else {
                    $this->io->writeln('<comment>No version selected. Nothing to perform.</comment>');
                }
            } else {
                $this->io->writeln('<comment>No upgrades are available. This is likely because you are already at</comment>');
                $this->io->writeln('<comment>the latest version permitted by the server.</comment>');
            }

            if ($instance->isLocked()) {
                $instance->unlock();
            }
        }
    }
}
