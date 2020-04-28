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
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Access\Access;
use TikiManager\Application\Discovery;
use TikiManager\Config\App;

class DetectInstanceCommand extends TikiManagerCommand
{
    protected $instances;
    protected $instancesInfo;

    protected function configure()
    {
        $this
            ->setName('instance:detect')
            ->setDescription('Detect Tiki branch or tag')
            ->setHelp('This command allows you to detect a Tiki branch or tag, for debugging purpose')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be detected, separated by comma (,)'
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
        if (empty($input->getOption('instances'))) {
            if (empty($this->instancesInfo)) {
                return;
            }

            CommandHelper::renderInstancesTable($output, $this->instancesInfo);
            $answer = $this->io->ask('Which instance(s) do you want to detect', null, function ($answer) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $this->instances);
                return implode(',', array_map(function ($elem) {
                    return $elem->getId();
                }, $selectedInstances));
            });

            $input->setOption('instances', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (empty($this->instancesInfo)) {
            $output->writeln('<comment>No instances available to detect.</comment>');
            return;
        }

        $instancesOption = $input->getOption('instances');

        CommandHelper::validateInstanceSelection($instancesOption, $this->instances);
        $instancesOption = explode(',', $instancesOption);
        $selectedInstances = array_intersect_key($this->instances, array_flip($instancesOption));

        /** @var Instance $instance */
        foreach ($selectedInstances as $instance) {
            $this->io->section($instance->name);
            if (! $instance->detectPHP()) {
                if ($instance->phpversion < 50300) {
                    $this->io->error('PHP Interpreter version is less than 5.3.');
                    continue;
                } else {
                    $this->io->error('PHP Interpreter could not be found on remote host.');
                    continue;
                }
            }

            $access = Access::getClassFor($instance->type);
            $access = new $access($instance);
            $discovery = new Discovery($instance, $access);
            $phpVersion = $discovery->detectPHPVersion();
            $this->io->writeln('<info>Instance PHP Version: ' . CommandHelper::formatPhpVersion($phpVersion) . '</info>');

            ob_start(); // Prevent output to be displayed
            $app = $instance->getApplication();

            if (!$app) {
                $this->io->writeln('<info>Blanck instance detected. Skipping...</info>');
                continue;
            }

            $branch = $instance->getApplication()->getBranch();
            if ($instance->branch != $branch) {
                $instance->updateVersion();
            };
            ob_end_clean();
            $this->io->writeln('<info>Detected ' .strtoupper($instance->vcs_type) . ': ' . $branch . '</info>');
        }
    }
}
