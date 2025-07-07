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
            ->addOption('phpversion', null, InputOption::VALUE_OPTIONAL, 'Specify a PHP version string to determine compatibility')
            ->setHelp('This command allows you to list all Tiki versions');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $vcsOption = $input->getOption('vcs');
        $vcsOption = $vcsOption ? strtoupper($vcsOption) : '';
        if (! in_array($vcsOption, ['GIT', 'SRC'])) {
            $vcsOption = '';
        }

        $instanceId = $input->getOption('instance');
        $upgrade = $input->getOption('upgrade');
        $phpExec = $input->getOption('phpexec');
        $viewFormat = $input->getOption('format');
        $filePath = $input->getOption('file');
        $phpVersion = $input->getOption('phpversion');

        $dataOutput = $output;
        if ($filePath) {
            $handle = fopen($filePath, 'w');
            if (! $handle) {
                $output->writeln('<error>Unable to open file for writing: ' . $filePath . '</error>');
                return 1;
            }
            $dataOutput = new StreamOutput($handle);
        }

        $forceColumn = false;
        $versions = [];
        $phpVersionStr = '';

        if ($instanceId) {
            $instance = Instance::getInstance($instanceId);
            if (! $instance) {
                $output->writeln("<error>No instance found with ID $instanceId</error>");
                return 1;
            }

            $access = $instance->getBestAccess('scripting');
            $instance->phpversion = intval($access->getInterpreterVersion($instance->phpexec));
            $phpVersionStr = CommandHelper::formatPhpVersion($instance->phpversion);

            $app = $instance->getApplication();
            $versions = $upgrade
                ? $app->getUpgradableVersions($instance->getLatestVersion(), true)
                : $app->getCompatibleVersions(false);
        } elseif ($phpExec || $phpVersion) {
            $instance = new Instance();
            $instance->type = 'local';
            $instance->vcs_type = $vcsOption ?: 'git';

            $localhost = new Local($instance);
            if ($phpExec) {
                $instance->phpexec = $phpExec;
                $instance->phpversion = intval($localhost->getInterpreterVersion($phpExec));
                $phpVersionStr = CommandHelper::formatPhpVersion($instance->phpversion);
            } else {
                $instance->phpversion = CommandHelper::phpVersionStringToId($phpVersion);
                $phpVersionStr = $phpVersion;
            }

            $tikiApplication = new Tiki($instance);
            $versions = $tikiApplication->getCompatibleVersions(false);
        } else {
            $forceColumn = true;
            $phpVersionStr = phpversion();
            $versions = CommandHelper::getVersions($vcsOption);
        }

        $versionsInfo = CommandHelper::getVersionsInfo($versions);

        if (!empty($versionsInfo)) {
            $this->io->newLine();
            CommandHelper::renderVersionsByFormat(
                $dataOutput,
                $versionsInfo,
                $viewFormat,
                $phpVersionStr,
                $forceColumn
            );
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
