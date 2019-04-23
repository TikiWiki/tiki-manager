<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
            ->setHelp('This command allows you to detect a Tiki branch or tag, for debugging purpose');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $io->newLine();
            $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

            $io->newLine();
            $output->writeln('<comment>In case you want to detect more than one instance, please use a comma (,) between the values</comment>');

            $helper = $this->getHelper('question');
            $question = CommandHelper::getQuestion('Which instance(s) do you want to detect', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
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
                ob_end_clean();
                $io->writeln('<info>Detected ' .strtoupper($instance->vcs_type) . ': ' . $branch . '</info>');
            }
        } else {
            $output->writeln('<comment>No instances available to detect.</comment>');
        }
    }
}
