<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Access\Access;
use TikiManager\Application\Discovery;

class DetectInstanceCommand extends Command
{
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

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('instances'))) {
            $io = new SymfonyStyle($input, $output);
            $instances = CommandHelper::getInstances();
            $instancesInfo = CommandHelper::getInstancesInfo($instances);

            if (empty($instancesInfo)) {
                return;
            }

            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $answer = $io->ask('Which instance(s) do you want to detect', null, function ($answer) use ($instances) {
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
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No instances available to detect.</comment>');
            return;
        }

        $instancesOption = $input->getOption('instances');

        CommandHelper::validateInstanceSelection($instancesOption, $instances);
        $instancesOption = explode(',', $instancesOption);
        $selectedInstances = array_intersect_key($instances, array_flip($instancesOption));

        foreach ($selectedInstances as $instance) {
            $io->section($instance->name);
            if (! $instance->detectPHP()) {
                if ($instance->phpversion < 50300) {
                    $io->error('PHP Interpreter version is less than 5.3.');
                    continue;
                } else {
                    $io->error('PHP Interpreter could not be found on remote host.');
                    continue;
                }
            }

            $access = Access::getClassFor($instance->type);
            $access = new $access($instance);
            $discovery = new Discovery($instance, $access);
            $phpVersion = $discovery->detectPHPVersion();
            CommandHelper::displayPhpVersion($phpVersion, $io);

            ob_start(); // Prevent output to be displayed
            $branch = $instance->getApplication()->getBranch();
            if ($instance->branch != $branch) {
                $new = $instance->getLatestVersion();
                $new->branch = $branch;
                $new->save();
            };
            ob_end_clean();
            $io->writeln('<info>Detected ' .strtoupper($instance->vcs_type) . ': ' . $branch . '</info>');
        }
    }
}
