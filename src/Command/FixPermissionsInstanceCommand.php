<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\App;

class FixPermissionsInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('instance:fixpermissions')
            ->setDescription('Fix permission on a Tiki instance')
            ->setHelp('This command allows you to fix permissions on a Tiki instance');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $output->writeln('<comment>Note: Only Tiki instances can have permissions fixed.</comment>');

            $this->io->newLine();
            $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

            $this->io->newLine();
            $output->writeln('<comment>In case you want to fix permissions to more than one instance, please use a comma (,) between the values</comment>');

            $helper = $this->getHelper('question');
            $question = CommandHelper::getQuestion('Which instance(s) do you want to fix permissions', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Fixing permissions for ' . $instance->name . '...</>');
                $instance->getApplication()->fixPermissions();
            }
        } else {
            $output->writeln('<comment>No Tiki instances available to fix permissions.</comment>');
        }
    }
}
