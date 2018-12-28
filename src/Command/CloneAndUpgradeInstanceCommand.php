<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class CloneAndUpgradeInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:cloneandupgrade')
            ->setDescription('Clone and upgrade instance')
            ->setHelp('This command allows you make another identical copy of Tiki with an extra upgrade operation')
            ->addArgument('mode', InputArgument::IS_ARRAY | InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('instance:clone');

        $argumentsToAdd = ['upgrade'];

        $args = $input->getArgument('mode');
        if (isset($args) && ! empty($args)) {
            $offset = $args[0] == 'upgrade' ? 1 : 0;
            $args = array_slice($args, $offset);

            $argumentsToAdd = array_merge($argumentsToAdd, $args);
        }

        $arguments = [
            'command' => 'instance:clone',
            'mode'    => $argumentsToAdd
        ];

        $verifyInstanceInput = new ArrayInput($arguments);
        $returnCode = $command->run($verifyInstanceInput, $output);
    }
}
