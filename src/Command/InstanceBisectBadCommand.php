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

class InstanceBisectBadCommand extends TikiManagerCommand
{
    protected $instances;
    protected $instancesInfo;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:bisect:bad')
            ->setDescription('Mark a commit as "bad" in an ongoing bisect session for a specified Tiki instance.')
            ->setHelp('This command marks a commit as "bad" within an active bisect session, indicating that the commit contains the bug or issue you are tracking.')
            ->addOption(
                'instance',
                'i',
                InputOption::VALUE_REQUIRED,
                'The Id of the Tiki instance to bisect'
            )
            ->addOption(
                'commit',
                null,
                InputOption::VALUE_OPTIONAL,
                'The commit Id that is known to be bad (optional, defaults to current commit if not provided)'
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
            $answer = $this->io->ask('Please select the Id of the instance');
            $input->setOption('instance', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (empty($this->instancesInfo)) {
            $this->io->info('No instances available');
            return Command::SUCCESS;
        }

        $instanceId = $input->getOption('instance');
        $badCommit = $input->getOption('commit');

        if (!$instanceId) {
            $output->writeln('<error>Instance Id is required.</error>');
            return 1;
        }

        $instance = Instance::getInstance($instanceId);

        if (!$instance) {
            $this->io->info("Instance with Id ({$instanceId}) not found.");
            return Command::SUCCESS;
        }

        $bisect = new BisectHelper($instance);

        $bisect->markCommitAsBad($badCommit);

        return 0;
    }
}
