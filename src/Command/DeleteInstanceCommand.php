<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;

class DeleteInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:delete')
            ->setDescription('Delete instance connection')
            ->setHelp('This command allows you to delete an instance connection');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $io->newLine();
            $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

            $io->newLine();
            $output->writeln('<comment>This will NOT delete the software itself, just your instance connection to it</comment>');

            $helper = $this->getHelper('question');
            $question = CommandHelper::getQuestion('Which instance(s) do you want to delete', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Deleting instance ' . $instance->name . '...</>');
                $instance->delete();
            }
        } else {
            $output->writeln('<comment>No instances available to delete.</comment>');
        }
    }
}
