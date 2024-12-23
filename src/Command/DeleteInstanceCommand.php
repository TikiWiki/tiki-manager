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

class DeleteInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

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
            $instances = CommandHelper::getInstances();
            $instancesInfo = CommandHelper::getInstancesInfo($instances);

            if (empty($instancesInfo)) {
                // execute will output message
                return;
            }

            $this->io->note('This will NOT delete the software itself, just your instance connection to it');
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $answer = $this->io->ask('Which instance(s) do you want to delete', null, function ($answer) use ($instances) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                return implode(',', CommandHelper::getInstanceIds($selectedInstances));
            });

            $input->setOption('instances', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available to delete.</comment>');
            return 0;
        }

        $instancesOption = $input->getOption('instances');

        $selectedInstances = CommandHelper::validateInstanceSelection($instancesOption, $instances);
        $hookName = $this->getCommandHook();
        foreach ($selectedInstances as $instance) {
            if ($instance->isInstanceProtected()) {
                $output->writeln(sprintf('<error>Operation aborted: The instance %s is protected using the \'sys_db_protected\' tag.</error>', $instance->name));
                continue; // Skip the deletion for the protected instance
            }
            $this->io->writeln(sprintf('<fg=cyan>Deleting instance %s...</>', $instance->name));
            $instance->delete();
            $this->io->writeln(sprintf('<info>Deleted instance %s</info>', $instance->name));
            $hookName->registerPostHookVars(['instance' => $instance]);
        }

        return 0;
    }
}
