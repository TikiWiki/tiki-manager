<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AccessInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:access')
            ->setDescription('Remote access to instance')
            ->setHelp('This command allows you to remotely access an instance');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = TrimHelper::getInstances();
        $instancesInfo = TrimHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $io->newLine();
            $renderResult = TrimHelper::renderInstancesTable($output, $instancesInfo);

            $io->newLine();
            $output->writeln('<comment>In case you want to access more than one instance, please use a comma (,) between the values</comment>');

            $helper = $this->getHelper('question');
            $question = TrimHelper::getQuestion('Which instance(s) do you want to access', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return TrimHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
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
