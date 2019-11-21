<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

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
                'check',
                null,
                InputOption::VALUE_NONE,
                'Check files checksum after operation has been performed.'
            )
            ->addOption(
                'source',
                's',
                InputOption::VALUE_REQUIRED,
                'Source instance.'
            )
            ->addOption(
                'target',
                't',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Destination instance(s).'
            )
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Select Branch.'
            )
            ->addOption(
                'direct',
                'd',
                InputOption::VALUE_NONE,
                'Prevent using the backup step and rsync source to target.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('instance:clone');

        $argumentsToAdd = ['upgrade'];

        $args = $input->getArgument('mode');
        if (isset($args) && !empty($args)) {
            $offset = $args[0] == 'upgrade' ? 1 : 0;
            $args = array_slice($args, $offset);

            $argumentsToAdd = array_merge($argumentsToAdd, $args);
        }

        $arguments = [
            'mode' => $argumentsToAdd,
        ];

        if ($input->getOption('check')) {
            $arguments['--check'] = true;
        }
        if ($source = $input->getOption("source")) {
            $arguments['--source'] = $source;
        }

        if ($target = $input->getOption("target")) {
            $arguments['--target'] = $target;
        }

        if ($branch = $input->getOption("branch")) {
            $arguments['--branch'] = $branch;
        }

        if ($direct = $input->getOption('direct')) {
            $arguments['--direct'] = $direct;
        }

        $verifyInstanceInput = new ArrayInput($arguments);
        $returnCode = $command->run($verifyInstanceInput, $output);

        return $returnCode;
    }
}
