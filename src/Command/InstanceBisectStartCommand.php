<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\BisectHelper;
use TikiManager\Command\Helper\CommandHelper;
use Symfony\Component\Console\Command\Command;
use TikiManager\Application\Instance;

class InstanceBisectStartCommand extends TikiManagerCommand
{
    protected $instances;
    protected $instancesInfo;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:bisect:start')
            ->setDescription('Start a bisect session for an instance to identify a commit that introduced a bug')
            ->setHelp('This command initiates a bisect session for a specified instance, setting up the initial state with a known good and bad commit.')
            ->addOption(
                'instance',
                'i',
                InputOption::VALUE_REQUIRED,
                'The Id of the Tiki instance to bisect'
            )
            ->addOption(
                'bad',
                null,
                InputOption::VALUE_REQUIRED,
                'The commit Id that is known to be bad'
            )
            ->addOption(
                'good',
                null,
                InputOption::VALUE_OPTIONAL,
                'The commit Id that is known to be good (optional, defaults to current commit if not provided)'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        $this->instances = $instances;
        $this->instancesInfo = $instancesInfo;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('instance'))) {
            if (empty($this->instancesInfo)) {
                return;
            }
            CommandHelper::renderInstancesTable($output, $this->instancesInfo);
            $answer = $this->io->ask('Please select the Id of the instance you want to bisect');
            $input->setOption('instance', $answer);
        }

        if (empty($input->getOption('bad'))) {
            $answer = $this->io->ask('Please enter the commit Id known to be bad');
            $input->setOption('bad', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (empty($this->instancesInfo)) {
            $this->io->info('No instances available');
            return Command::SUCCESS;
        }

        $instanceId = $input->getOption('instance');
        $badCommit = $input->getOption('bad');
        $goodCommit = $input->getOption('good');

        if (!$instanceId || !$badCommit) {
            $output->writeln('<error>Instance Id and bad commit are required.</error>');
            return 1;
        }

        $instance = Instance::getInstance($instanceId);

        if (!$instance) {
            $this->io->info("Instance with Id ({$instanceId}) not found.");
            return Command::SUCCESS;
        }

        $bisect = new BisectHelper($instance);

        $bisect->initializeAndStartSession($badCommit, $goodCommit);

        return 0;
    }
}
