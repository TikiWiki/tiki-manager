<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\App;

class CopySshKeyCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:copysshkey')
            ->setDescription('Copy SSH key')
            ->setHelp('This command allows you copy the SSH key to the remote instance');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $this->io->newLine();
            $output->writeln('<comment>In case you want to copy the SSH key to more than one instance, please use a comma (,) between the values</comment>');

            $helper = $this->getHelper('question');
            $question = CommandHelper::getQuestion('Which instance(s) do you want to copy the SSH key', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $hookName = $this->getCommandHook();
            $selectedInstances = $helper->ask($input, $output, $question);
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Copying SSH key to ' . $instance->name . '... (use "exit" to move to next the instance)</>');
                $access = $instance->getBestAccess('scripting');
                $access->firstConnect();
                $hookName->registerPostHookVars(['instance' => $instance]);
            }
        } else {
            $output->writeln('<comment>No instances available to copy the SSH key.</comment>');
        }
        return 0;
    }
}
