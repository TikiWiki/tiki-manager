<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BlankInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('instance:blank')
            ->setDescription('Creates a new blank instance')
            ->setHelp('This command allows you to create a new blank instance without actually add a Tiki');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command_name = 'manager:instance:create';
        if (! $this->getApplication()->has($command_name)) {
            $command_name = 'instance:create';
        }

        $command = $this->getApplication()->find($command_name);

        $arguments = [
            'command' => $command_name,
            '--blank' => true
        ];

        $blankInstanceInput = new ArrayInput($arguments);
        return $command->run($blankInstanceInput, $output);
    }
}
