<?php
/**
 * @copyright
 * (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 * See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;

class TagListCommand extends TikiManagerCommand
{
    protected $instances;
    protected $instancesInfo;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('tag:list')
            ->setDescription('Lists all tags associated with a specified instance.')
            ->setHelp('This command allows you to list all tags for an instance')
            ->addOption('instance', 'i', InputOption::VALUE_REQUIRED, 'The Id of the instance')
            ->addOption('tag-name', 'N', InputOption::VALUE_OPTIONAL, 'The name of the tag to retrieve its value');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->instances = CommandHelper::getInstances();
        $this->instancesInfo = CommandHelper::getInstancesInfo($this->instances);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {

        if (empty($input->getOption('instance'))) {
            if (empty($this->instancesInfo)) {
                return;
            }
            CommandHelper::renderInstancesTable($output, $this->instancesInfo);
            while (true) {
                $instanceId = $this->io->ask('For which instance do you want to list tags?');
                if (!array_key_exists($instanceId, $this->instances)) {
                    $this->io->error("Invalid Or Bad Instance Id");
                    continue;
                }
                $input->setOption('instance', $instanceId);
                break;
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (empty($this->instancesInfo)) {
            $this->io->info('No instances available');
            return Command::SUCCESS;
        }

        $instanceId = $input->getOption('instance');
        $tagName = $input->getOption('tag-name');

        if (!array_key_exists($instanceId, $this->instances)) {
            $this->io->error("Invalid Or Bad Instance Id");
            return Command::FAILURE;
        }

        $instance = Instance::getInstance($instanceId);

        if (!$instance) {
            $this->io->info("Instance with Id ({$instanceId}) not found.");
            return Command::SUCCESS;
        }

        $tags = $instance->getInstanceTags($tagName);

        if (empty($tags)) {
            $message = $tagName ? "Tag '{$tagName}' not found" : "No tags found";
            $this->io->info("{$message} for instanceId ({$instanceId}).");
            return Command::SUCCESS;
        }

        $headerText = $tagName ? "Tag Name with Tag Value for instanceId ({$instanceId}):" : "Tags for instanceId ({$instanceId}):";
        $this->io->text("{$headerText}\n");
        CommandHelper::renderInstanceTags($output, $tags, empty($tagName));

        return Command::SUCCESS;
    }
}
