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
                'skip-checksum',
                null,
                InputOption::VALUE_NONE,
                'Skip files checksum check for a faster result. Files checksum change won\'t be saved on the DB.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('instance:update');

        $arguments = [
            'mode'    => 'switch'
        ];

        if ($input->getOption('skip-checksum')) {
            $arguments['--skip-checksum'] = true;
        }

        $verifyInstanceInput = new ArrayInput($arguments);
        $returnCode = $command->run($verifyInstanceInput, $output);
    }
}
