<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpgradeInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:upgrade')
            ->setDescription('Upgrade instance')
            ->setHelp('This command allows you to upgrade an instance')
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check files checksum after operation has been performed.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('instance:update');

        $arguments = [
            'mode'    => 'switch'
        ];

        if ($input->getOption('check')) {
            $arguments['--check'] = true;
        }

        $verifyInstanceInput = new ArrayInput($arguments);
        $returnCode = $command->run($verifyInstanceInput, $output);
    }
}
