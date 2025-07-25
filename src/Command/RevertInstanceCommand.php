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
use TikiManager\Hooks\InstanceRevertHook;

class RevertInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:revert')
            ->setDescription('Revert instance state')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs (or names) to be reverted, separated by comma (,). You can also use the "all" keyword.'
            )
            ->addOption(
                'validate',
                null,
                InputOption::VALUE_NONE,
                'Attempt to validate the instance by checking its URL.'
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
                return implode(',', CommandHelper::getInstanceIds($selectedInstances));
            });

            $input->setOption('instances', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available to revert.</comment>');
            return 0;
        }

        $instancesOption = $input->getOption('instances');

        $selectedInstances = CommandHelper::validateInstanceSelection($instancesOption, $instances);
        foreach ($selectedInstances as $instance) {
            $this->io->writeln(sprintf('<fg=cyan>Reverting instance %s...</>', $instance->name));
            $instance->revert();
        }

        if ($input->getOption('validate')) {
            $hookName = $this->getCommandHook();
            $InstanceRevertHook = new InstanceRevertHook($hookName->getHookName(), $this->logger);
            CommandHelper::validateInstances($selectedInstances, $InstanceRevertHook);
        }

        return 0;
    }
}
