<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Application\Patch;
use TikiManager\Command\Helper\CommandHelper;

class ListPatchCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('instance:patch:list')
            ->setDescription('List patches applied to an instance')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be checked, separated by comma (,)'
            )
            ->setHelp('This command allows you to check local patches applied to an instance codebase');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($input->getOption('instances'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $this->io->newLine();
            $output->writeln('<comment>Note: Only Tiki instances can have patches applied</comment>');
            $this->io->newLine();
            $output->writeln('<comment>In case you want to check more than one instance, please use a comma (,) between the values</comment>');
            $answer = $this->io->ask('Which instance(s) do you want to check for local patches', null, function ($answer) use ($instances) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                return implode(',', array_map(function ($elem) {
                    return $elem->getId();
                }, $selectedInstances));
            });

            $input->setOption('instances', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (!isset($instancesInfo)) {
            throw new \RuntimeException('No Tiki instances available to check for local patches.');
        }

        $selectedInstances = CommandHelper::validateInstanceSelection($input->getOption('instances'), $instances);

        foreach ($selectedInstances as $instance) {
            $this->io->section(sprintf('Instance %s local patches:', $instance->name));
            $patches = Patch::getPatches($instance->getId());
            $rows = [];
            foreach ($patches as $patch) {
                $rows[] = [
                    'id' => $patch->id,
                    'instance' => $instance->name,
                    'package' => $patch->package,
                    'url' => $patch->url
                ];
            }
            if ($rows) {
                $table = new Table($output);
                $headers = array_map(function ($headerValue) {
                    return ucwords($headerValue);
                }, array_keys($rows[0]));
                $table
                    ->setHeaders($headers)
                    ->setRows($rows);
                $table->render();
            } else {
                $this->io->writeln('No patches applied on this instance.');
            }
        }

        return 0;
    }
}
