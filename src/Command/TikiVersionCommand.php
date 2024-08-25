<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\StreamOutput;
use TikiManager\Access\Local;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Application\Instance;
use TikiManager\Application\Tiki;

class TikiVersionCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('tiki:versions')
            ->setDescription('List Tiki versions')
            ->addOption('vcs', null, InputOption::VALUE_OPTIONAL, 'Filter versions by Version Control System')
            ->addOption('instance', 'i', InputOption::VALUE_OPTIONAL, 'Filter versions compatible with a specified instance ID')
            ->addOption('upgrade', 'u', InputOption::VALUE_NONE, 'Filter only upgradable versions')
            ->addOption('phpexec', null, InputOption::VALUE_OPTIONAL, 'Specify PHP executable to determine compatibility')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (table or simple)', 'table')
            ->addOption('file', null, InputOption::VALUE_OPTIONAL, 'Output file to write results')
            ->setHelp('This command allows you to list all Tiki versions');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $vcsOption = $input->getOption('vcs');
        $vcsOption = $vcsOption ? strtoupper($vcsOption) : '';
        if (! in_array($vcsOption, ['SVN', 'GIT', 'SRC'])) {
            $vcsOption = '';
        }

        $instance = null;
        $versions = [];
        $instanceId = $input->getOption('instance');
        $upgrade = $input->getOption('upgrade');
        $phpExec = $input->getOption('phpexec');
        $viewFormat = $input->getOption('format');
        $filePath = $input->getOption('file');

        $dataOutput = $output;
        if ($filePath) {
            $handle = fopen($filePath, 'w');
            if (! $handle) {
                $output->writeln('<error>Unable to open file for writing: ' . $filePath . '</error>');
                return 1;
            }
            $dataOutput = new StreamOutput($handle);
        }

        if ($instanceId) {
            $instance = Instance::getInstance($instanceId);
            if (! $instance) {
                $output->writeln("<error>No instance found with ID $instanceId</error>");
                return 1;
            }
            $access = $instance->getBestAccess('scripting');
            $instance->phpversion = intval($access->getInterpreterVersion($instance->phpexec));
            $app = $instance->getApplication();
            if ($upgrade) {
                $currentVersion = $instance->getLatestVersion();
                $versions = $app->getUpgradableVersions($currentVersion, true);
            } else {
                $versions = $app->getCompatibleVersions(false);
            }
        } elseif ($phpExec) {
            $instance = new Instance();
            $instance->type = 'local';
            $instance->vcs_type = 'git';
            if (! empty($vcsOption)) {
                $instance->vcs_type = $vcsOption;
            }
            $instance->phpexec = $phpExec;
            $localhost = new Local($instance);
            $phpVersionNumber = $localhost->getInterpreterVersion($phpExec);
            $instance->phpversion = intval($phpVersionNumber);
            $tikiApplication = new Tiki($instance);
            $versions = $tikiApplication->getCompatibleVersions(false);
        } else {
            $versions = CommandHelper::getVersions($vcsOption);
        }

        $versionsInfo = CommandHelper::getVersionsInfo($versions);
        if (isset($versionsInfo)) {
            $this->io->newLine();
            CommandHelper::renderVersionsByFormat($dataOutput, $versionsInfo, $viewFormat);
        } else {
            $output->writeln('<comment>No versions available to list.</comment>');
        }

        if (isset($handle)) {
            fflush($handle);
            fclose($handle);
        }

        return 0;
    }
}
