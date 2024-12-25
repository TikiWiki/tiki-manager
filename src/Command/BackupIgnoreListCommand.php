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

class BackupIgnoreListCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('backups:ignore:list')
            ->setDescription('List excluded files/folders from backup folder')
            ->addOption(
                'instance',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Instance ID.'
            )
            ->addOption('json')
            ->setHelp('This command allows you to list all excluded files/folders from backup folder');
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
            $list = Instance::getBackupIgnoreList();
            CommandHelper::renderBackupIgnoreListTable($output, $list);
            return 0;
        }

        CommandHelper::validateInstanceSelection($instanceId, $instances);

        $ignoreLists = Instance::getBackupIgnoreList([':instance_id' => $instanceId]);

        if ($input->getOption('json')) {
            $output->writeln(json_encode($ignoreLists));
            return 0;
        }

        if (!empty($ignoreLists)) {
            $this->io->newLine();
            CommandHelper::renderBackupIgnoreListTable($output, $ignoreLists);
        } else {
            $output->writeln('<comment>No ignore list available for this instance.</comment>');
        }

        return 0;
    }
}