<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;

class ListInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:list')
            ->setDescription('List instances')
            ->addOption('json')
            ->setHelp('This command allows you to list all managed instances');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances) ?? [];

        if ($input->getOption('json')) {
            $output->write(json_encode($instancesInfo));
            return;
        }

        if (!empty($instancesInfo)) {
            $io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);
        } else {
            $output->writeln('<comment>No instances available to list.</comment>');
        }
    }
}
