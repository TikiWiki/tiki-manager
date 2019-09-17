<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;

class AccessInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:access')
            ->setDescription('Remote access to instance')
            ->setHelp('This command allows you to remotely access an instance')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_OPTIONAL,
                'List of instance IDs to be checked, separated by comma (,)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $instancesOption = $input->getOption('instances');
            if (empty($instancesOption)) {
                $io->newLine();
                $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

                $io->newLine();
                $output->writeln('<comment>In case you want to access more than one instance, please use a comma (,) between the values</comment>');

                $helper = $this->getHelper('question');
                $question = CommandHelper::getQuestion('Which instance(s) do you want to access', null, '?');
                $question->setValidator(function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                });

                $selectedInstances = $helper->ask($input, $output, $question);
            } else {
                CommandHelper::validateInstanceSelection($instancesOption, $instances);
                $instancesOption = explode(',', $instancesOption);
                $selectedInstances = array_intersect_key($instances, array_flip($instancesOption));
            }

            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Connecting to ' . $instance->name . ' at ' . $instance->webroot . ' directory... (use "exit" to move to next the instance)</>');
                $access = $instance->getBestAccess('scripting');
                $access->openShell($instance->webroot);
            }
        } else {
            $output->writeln('<comment>No instances available to access.</comment>');
        }
    }
}
