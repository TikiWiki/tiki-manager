<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class CloneAndUpgradeInstanceCommand extends TikiManagerCommand
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
                'skip-reindex',
                null,
                InputOption::VALUE_NONE,
                'Skip rebuilding index step.'
            )
            ->addOption(
                'skip-cache-warmup',
                null,
                InputOption::VALUE_NONE,
                'Skip generating cache step.'
            )
            ->addOption(
                'live-reindex',
                null,
                InputOption::VALUE_OPTIONAL,
                'Live reindex, set instance maintenance off and after perform index rebuild.',
                true
            )
            ->addOption(
                'direct',
                'd',
                InputOption::VALUE_NONE,
                'Prevent using the backup step and rsync source to target.'
            )
            ->addOption(
                'keep-backup',
                null,
                InputOption::VALUE_NONE,
                'Source instance backup is not deleted before the process finished.'
            )
            ->addOption(
                'use-last-backup',
                null,
                InputOption::VALUE_NONE,
                'Use source instance last created backup.'
            )->addOption(
                'db-host',
                'dh',
                InputOption::VALUE_REQUIRED,
                'Target instance database host'
            )
            ->addOption(
                'db-user',
                'du',
                InputOption::VALUE_REQUIRED,
                'Target instance database user'
            )
            ->addOption(
                'db-pass',
                'dp',
                InputOption::VALUE_REQUIRED,
                'Target instance database password'
            )
            ->addOption(
                'db-prefix',
                'dpx',
                InputOption::VALUE_REQUIRED,
                'Target instance database prefix'
            )
            ->addOption(
                'db-name',
                'dn',
                InputOption::VALUE_REQUIRED,
                'Target instance database name'
            )
            ->addOption(
                'stash',
                null,
                InputOption::VALUE_NONE,
                'Saves your local modifications, and try to apply after update/upgrade'
            )
            ->addOption(
                'timeout',
                null,
                InputOption::VALUE_OPTIONAL,
                'Modify the default command execution timeout from 3600 seconds to a custom value'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command_name = 'manager:instance:clone';
        if (!$this->getApplication()->has($command_name)) {
            $command_name = 'instance:clone';
        }
        $command = $this->getApplication()->find($command_name);

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

        foreach ($input->getOptions() as $key => $value) {
            if ($value) {
                $arguments['--' . $key] = $value;
            }
        }

        $verifyInstanceInput = new ArrayInput($arguments);
        $verifyInstanceInput->setInteractive($input->isInteractive());

        return $command->run($verifyInstanceInput, $output);
    }
}
