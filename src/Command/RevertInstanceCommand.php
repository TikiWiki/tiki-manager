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
use TikiManager\Config\App;

class RevertInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('instance:revert')
            ->setDescription('Revert instance state')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be reverted, separated by comma (,)'
            )
            ->setHelp('This command allows you to revert a version controlled instance to its original state (e.g. remove all locally applied patches or modifications)');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('instances'))) {
            $instances = CommandHelper::getInstances();
            $instancesInfo = CommandHelper::getInstancesInfo($instances);

            if (empty($instancesInfo)) {
                // execute will output message
                return;
            }

            $this->io->note('Your local modifications to the files will be deleted. Make sure you have a backup or commit them.');
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $answer = $this->io->ask('Which instance(s) do you want to revert', null, function ($answer) use ($instances) {
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
        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available to revert.</comment>');
            return 0;
        }

        $instancesOption = $input->getOption('instances');

        CommandHelper::validateInstanceSelection($instancesOption, $instances);
        $instancesOption = explode(',', $instancesOption);
        $selectedInstances = array_intersect_key($instances, array_flip($instancesOption));

        foreach ($selectedInstances as $instance) {
            $this->io->writeln(sprintf('<fg=cyan>Reverting instance %s...</>', $instance->name));
            $instance->revert();
        }
        return 0;
    }
}
