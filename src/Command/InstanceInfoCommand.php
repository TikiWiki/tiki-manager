<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;

class InstanceInfoCommand extends TikiManagerCommand
{
    protected $instances;
    protected $instancesInfo;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:info')
            ->setDescription('Show Tiki instance Info')
            ->setHelp('This command allows you to show Tiki instance information, and can get information on json format')
            ->addOption('instance', 'i', InputOption::VALUE_REQUIRED, 'Sources instance')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (table or json)', 'table');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $instances = CommandHelper::getInstances();
        $this->instancesInfo = CommandHelper::getInstancesInfo($instances);

        $this->instances = $instances;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('instance'))) {
            if (empty($this->instancesInfo)) {
                return;
            }

            CommandHelper::renderInstancesTable($output, $this->instancesInfo);
            $answer = $this->io->ask('Which instance do you want to get info for', null, function ($answer) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $this->instances);
                return implode(',', array_map(function ($elem) {
                    return $elem->getId();
                }, $selectedInstances));
            });

            $input->setOption('instance', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (empty($this->instancesInfo)) {
            $output->writeln('<comment>No instances available to detect.</comment>');
            return 0;
        }
        $instancesOption = $input->getOption('instance');
        $filtered_instances = array_filter($this->instances, function ($instance) use ($instancesOption) {
            return( $instance->id == $instancesOption || $instance->name === $instancesOption);
        });
        $selectedinstance = CommandHelper::getInstancesInfo($filtered_instances, true);

        if (strtolower($input->getOption('format')) == 'json') {
            $output->writeln(json_encode($selectedinstance));
        } else {
            CommandHelper::renderInstancesTable($output, $selectedinstance, true);
        }
        return 0;
    }
}
