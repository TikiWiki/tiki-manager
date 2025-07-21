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

class TagAddOrEditCommand extends TikiManagerCommand
{
    protected $instances;
    protected $instancesInfo;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('tag:edit')
            ->setDescription('Adds or updates a tag for a specified instance.')
            ->setHelp('This command allows you to add a new tag or update an existing tag for an instance.')
            ->addOption('instance', 'i', InputOption::VALUE_REQUIRED, 'The Id of the instance')
            ->addOption('tag-name', 'N', InputOption::VALUE_REQUIRED, 'The name of the tag')
            ->addOption('tag-value', "T", InputOption::VALUE_REQUIRED, 'The value of the tag');
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
                $instanceId = $this->io->ask('For which instance do you want to add/edit a tag?');
                if (!array_key_exists($instanceId, $this->instances)) {
                    $this->io->error("Invalid Or Bad Instance Id");
                    continue;
                }
                $input->setOption('instance', $instanceId);
                break;
            }
        }

        // Prompt for tag name if not provided, and ensure it's a string
        if (empty($input->getOption('tag-name'))) {
            do {
                $tagName = $this->io->ask('Enter the name of the tag');
                if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $tagName)) {
                    $this->io->error('Tag name must consist of letters, digits, hyphens, underscores, and periods only.');
                    $tagName = null;
                } elseif (is_numeric($tagName) || trim($tagName) === '') {
                    $this->io->error('Tag name must be a non-empty string.');
                    $tagName = null;
                }
            } while ($tagName === null);
            $input->setOption('tag-name', $tagName);
        }

        // Prompt for tag value if not provided, and ensure it's a string
        if (empty($input->getOption('tag-value'))) {
            do {
                $tagValue = $this->io->ask('Enter the value of the tag');
                if (trim($tagValue) === '') {
                    $this->io->error('Tag value can not be empty.');
                    $tagValue = null;
                }
            } while ($tagValue === null);
            $input->setOption('tag-value', $tagValue);
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
        $tagValue = $input->getOption('tag-value');

        if (!array_key_exists($instanceId, $this->instances)) {
            $this->io->error("Invalid Or Bad Instance Id");
            return Command::FAILURE;
        }

        if (!preg_match('/^[a-zA-Z0-9-_.]+$/', $tagName)) {
            $this->io->error("Tag name must consist of letters, digits, hyphens, underscores, and periods only.");
            return Command::FAILURE;
        }

        if (is_numeric($tagName)) {
            $this->io->error("Tag name cannot be purely numeric.");
            return Command::FAILURE;
        }

        $instance = Instance::getInstance($instanceId);

        if (!$instance) {
            $this->io->info("Instance with Id ({$instanceId}) not found.");
            return Command::SUCCESS;
        }

        $tag = $instance->getInstanceTags($tagName);

        $action = count($tag) ? 'Updated' : 'Created';

        if (!$instance->addOrUpdateInstanceTag($tagName, $tagValue)) {
            $this->io->error("Failed to add/update tag '{$tagName}' for an instance ({$instanceId}).");
            return Command::FAILURE;
        }

        $this->io->success("Tag '{$tagName}' with value '{$tagValue}' for instanceId ({$instanceId}) is {$action}");
        return Command::SUCCESS;
    }
}
