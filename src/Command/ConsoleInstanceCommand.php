<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;

class ConsoleInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:console')
            ->setDescription('Run Tiki console commands')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to run command on, separated by comma (,)'
            )
            ->addOption(
                'command',
                'c',
                InputOption::VALUE_REQUIRED,
                'Command that will run in the selected instance(s)'
            )
            ->setHelp('This command allows you to run any console command from Tiki');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        if (empty($input->getOption('instances'))) {
            $instances = CommandHelper::getInstances('tiki');
            $instancesInfo = CommandHelper::getInstancesInfo($instances);

            if (empty($instancesInfo)) {
                return;
            }

            $io->writeln('<comment>Note: Only Tiki instances can run Console commands.</comment>');

            $io->newLine();
            $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

            $io->newLine();
            $io->writeln('<comment>In case you want to run Console commands in more than one instance, please use a comma (,) between the values</comment>');
            $io->writeln('<comment>Note: If you write \'help\' you can check the list of commands</comment>');

            $selectedInstances = $io->ask(
                'Which instance(s) do you want run Console commands?',
                null,
                function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                }
            );

            $selectedInstances = implode(',', array_map(function ($elem) {
                return $elem->getId();
            }, $selectedInstances));

            $input->setOption('instances', $selectedInstances);
        }

        if (empty($input->getOption('command'))) {
            $command = $io->ask('Write command to execute');
            $input->setOption('command', $command);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No Tiki instances available to run Console commands.</comment>');
            return;
        }

        $instancesOption = $input->getOption('instances');

        CommandHelper::validateInstanceSelection($instancesOption, $instances);
        $instancesOption = explode(',', $instancesOption);
        $selectedInstances = array_intersect_key($instances, array_flip($instancesOption));

        $command = $input->getOption('command');
        $command = $command == 'help' ? '' : $command; // Normalize command

        foreach ($selectedInstances as $instance) {
            $output->writeln('<fg=cyan>Calling command in ' . $instance->name . '</>');

            $access = $instance->getBestAccess('scripting');
            $access->chdir($instance->webroot);
            $new = $access->shellExec(
                ["{$instance->phpexec} -q -d memory_limit=256M console.php " . $command],
                true
            );
            if ($new) {
                $output->writeln('<fg=cyan>Result:</>');
                $output->writeln($new);
            }
        }
    }
}
