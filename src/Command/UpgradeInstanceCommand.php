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
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Command\Traits\InstanceUpgrade;
use TikiManager\Libs\Helpers\Checksum;

class UpgradeInstanceCommand extends TikiManagerCommand
{
    use InstanceUpgrade;

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
            )
            ->addOption(
                'stash',
                null,
                InputOption::VALUE_NONE,
                'Saves your local modifications, and try to apply after update/upgrade'
            )
            ->addOption(
                'ignore-requirements',
                null,
                InputOption::VALUE_NONE,
                'Ignore version requirements. Allows to select non-supported branches, useful for testing.'
            );
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $checksumCheck = $input->getOption('check') ?? false;
        $instancesOption = $input->getOption('instances');
        $instances = CommandHelper::getInstances('update');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        $skipReindex = $input->getOption('skip-reindex');
        $skipCache = $input->getOption('skip-cache-warmup');
        $liveReindex = $input->getOption('live-reindex');
        $lag = $input->getOption('lag');
        $vcsOptions = [
            'allow_stash' => $input->getOption('stash')
        ];

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

            $instanceVCS = $instance->getVersionControlSystem();
            $instanceVCS->setLogger($this->logger);
            $instanceVCS->setVCSOptions($vcsOptions);

            $app = $instance->getApplication();
            $branchName = $instance->getLatestVersion()->getBranch();

            $this->io->writeln('<fg=cyan>You are currently running: ' . $branchName . '</>');

            $branch = $input->getOption('branch');
            $skipRequirements = $input->getOption('ignore-requirements') ?? false;
            $selectedVersion = $this->getUpgradeVersion($instance, !$skipRequirements, $branch);

            if (!$selectedVersion) {
                continue;
            }

            try {
                $instance->lock();
                $filesToResolve = $app->performUpgrade($instance, $selectedVersion, [
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
                $this->logger->error('Failed to upgrade instance!', [
                    'instance' => $instance->name,
                    'exception' => $e,
                ]);
                CommandHelper::setInstanceSetupError($instance->id, $e);
                continue;
            }

            if ($instance->isLocked()) {
                $instance->unlock();
            }
        }
    }
}
