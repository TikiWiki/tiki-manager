<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;

class DeleteInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:delete')
            ->setDescription('Delete instance connection')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be deleted, separated by comma (,)'
            )
            ->setHelp('This command allows you to delete an instance connection');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('instances'))) {
            $io = new SymfonyStyle($input, $output);
            $instances = CommandHelper::getInstances();
            $instancesInfo = CommandHelper::getInstancesInfo($instances);

            if (empty($instancesInfo)) {
                // execute will output message
                return;
            }

            $io->note('This will NOT delete the software itself, just your instance connection to it');
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $answer = $io->ask('Which instance(s) do you want to delete', null, function ($answer) use ($instances) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                return implode(',', array_map(function ($elem) {
                    return $elem->getId();
                }, $selectedInstances));
            });

            $input->setOption('instances', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available to delete.</comment>');
            return;
        }

        $instancesOption = $input->getOption('instances');

        CommandHelper::validateInstanceSelection($instancesOption, $instances);
        $instancesOption = explode(',', $instancesOption);
        $selectedInstances = array_intersect_key($instances, array_flip($instancesOption));

        foreach ($selectedInstances as $instance) {
            $io->writeln(sprintf('<fg=cyan>Deleting instance %s...</>', $instance->name));
            $instance->delete();
            $io->writeln(sprintf('<info>Deleted instance %s</info>', $instance->name));
        }
    }
}
