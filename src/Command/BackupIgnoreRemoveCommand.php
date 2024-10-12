<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Application\Instance;

class BackupIgnoreRemoveCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('backups:ignore:remove')
            ->setDescription('Remove excluded files/folders list from backup folder')
            ->setHelp('This command allows you to remove excluded files/folders from backup folder')
            ->addOption(
                'instance',
                'i',
                InputOption::VALUE_REQUIRED,
                'Instance ID.'
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Remove all ignored files/folders for the specified instance.'
            )
            ->addArgument(
                'paths',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'File/Folder paths to remove from the backup ignore list (separated by space).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available.</comment>');
            return 0;
        }

        $instanceId = $input->getOption('instance');
        if (empty($instanceId)) {
            $this->io->error('Instance can not be empty.');
            return 1;
        }

        $paths = $input->getArgument('paths');
        $isAllOptionSet = $input->getOption('all');

        if (empty($paths) && !$isAllOptionSet) {
            $this->io->error('Either --all or at least one path must be provided.');
            return 1;
        }

        if (!empty($paths) && $isAllOptionSet) {
            $this->io->error('Cannot use --all and specific paths together.');
            return 1;
        }

        $instance = Instance::getInstance($instanceId);
        if (!$instance) {
            $this->io->error("Instance with Id ({$instanceId}) not found.");
            return 1;
        }

        if ($isAllOptionSet) {
            $instance->removeBackupIgnoreList();
            $output->writeln('<comment>All entries in the backup ignore list have been deleted successfully.</comment>');
        }

        if (!empty($paths)) {
            $instance->removeBackupIgnoreList($paths);
            $output->writeln('<comment>Specified paths have been removed from the backup ignore list.</comment>');
        }

        return 0;
    }
}
