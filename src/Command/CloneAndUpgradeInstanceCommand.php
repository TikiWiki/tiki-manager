<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->addArgument('mode', InputArgument::IS_ARRAY | InputArgument::OPTIONAL)
            ->addOption(
                'skip-checksum',
                null,
                InputOption::VALUE_NONE,
                'Skip files checksum check for a faster result. Files checksum change won\'t be saved on the DB.'
            );
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
            'mode' => $argumentsToAdd,
        ];

        if ($input->getOption('skip-checksum')) {
            $arguments['--skip-checksum'] = true;
        }

        $verifyInstanceInput = new ArrayInput($arguments);
        $returnCode = $command->run($verifyInstanceInput, $output);
    }
}
