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
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Application\Instance;

class BackupIgnoreAddCommand extends TikiManagerCommand
{
    protected $instances;
    protected $instancesInfo;

    protected function configure()
    {
        $this
            ->setName('backups:ignore:add')
            ->setDescription('Exclude some files/folders from backup folder')
            ->setHelp('This command allows you to exclude some files/folders from backup folder')
            ->addOption(
                'instance',
                'i',
                InputOption::VALUE_REQUIRED,
                'Instance ID.'
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'File/Folder to ignore while backup, separated by comma (,)'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->instances = CommandHelper::getInstances();
        $this->instancesInfo = CommandHelper::getInstancesInfo($this->instances);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('instance'))) {
            if (empty($this->instancesInfo)) {
                return;
            }
            CommandHelper::renderInstancesTable($output, $this->instancesInfo);
            while (true) {
                $instanceId = $this->io->ask('For which instance do you want to add files/folders to be ignored?');
                if (!array_key_exists($instanceId, $this->instances)) {
                    $this->io->error("Invalid Or Bad Instance Id");
                    continue;
                }
                $input->setOption('instance', $instanceId);
                break;
            }
        }

        $paths = $input->getOption('path');
        if (empty($paths)) {
            $paths = $this->io->ask('Enter file/folder path to be ignored');
            while (empty($paths)) {
                $this->io->error("Path cannot be empty.");
                $paths = $this->io->ask('Enter file/folder path to be ignored');
            }
            $input->setOption('path', $paths);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (empty($this->instancesInfo)) {
            $output->writeln('<comment>No instances available.</comment>');
            return 0;
        }

        $instanceId = $input->getOption('instance');
        if (empty($instanceId)) {
            $this->io->error('Instance Id can not be empty.');
            return 1;
        }

        if (!array_key_exists($instanceId, $this->instances)) {
            $this->io->error("Invalid Or Bad Instance Id dfdf");
            return 1;
        }

        $paths = $input->getOption('path');
        if (empty($paths)) {
            $this->io->error('Path cannot be empty.');
            return 1;
        }

        $instance = Instance::getInstance($instanceId);
        if (!$instance) {
            $this->io->error("Instance with Id ({$instanceId}) not found.");
            return 1;
        }

        $paths = explode(',', $paths);
        $instance->addPathToIgnoreList($paths);

        $this->io->success('Backup ignore list added.');
        return 0;
    }
}
