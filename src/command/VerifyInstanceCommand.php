<?php

namespace App\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VerifyInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:verify')
            ->setDescription('Verify instance')
            ->setHelp('This command allows you to verify an instance (same as check)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('instance:check');

        $arguments = [
            'command' => 'instance:check'
        ];

        $verifyInstanceInput = new ArrayInput($arguments);
        $returnCode = $command->run($verifyInstanceInput, $output);
    }
}
