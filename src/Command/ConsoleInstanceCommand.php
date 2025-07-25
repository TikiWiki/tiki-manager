<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\App;

class ConsoleInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:console')
            ->setDescription('Run Tiki console commands')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs (or names) to run command on, separated by comma (,). You can also use the "all" value".'
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
        if (empty($input->getOption('instances'))) {
            $instances = CommandHelper::getInstances('tiki');
            $instancesInfo = CommandHelper::getInstancesInfo($instances);

            if (empty($instancesInfo)) {
                return;
            }

            $this->io->writeln('<comment>Note: Only Tiki instances can run Console commands.</comment>');

            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $this->io->newLine();
            $this->io->writeln('<comment>In case you want to run Console commands in more than one instance, please use a comma (,) between the values</comment>');
            $this->io->writeln('<comment>Note: If you write \'help\' you can check the list of commands</comment>');

            $selectedInstances = $this->io->ask(
                'Which instance(s) do you want run Console commands?',
                null,
                function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                }
            );

            $input->setOption('instances', implode(',', CommandHelper::getInstanceIds($selectedInstances)));
        }

        if (empty($input->getOption('command'))) {
            $command = $this->io->ask('Write command to execute');
            $input->setOption('command', $command);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $instances = CommandHelper::getInstances('tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($instancesInfo)) {
            $output->writeln('<comment>No Tiki instances available to run Console commands.</comment>');
            return 0;
        }

        $instancesOption = $input->getOption('instances');

        $selectedInstances = CommandHelper::validateInstanceSelection($instancesOption, $instances);
        $command = $input->getOption('command');
        $command = $command == 'help' ? '' : $command; // Normalize command

        $hookName = $this->getCommandHook();
        foreach ($selectedInstances as $instance) {
            $output->writeln('<fg=cyan>Calling command in ' . $instance->name . '</>');
            $hookName->registerPostHookVars(['instance' => $instance]);

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

        return 0;
    }
}
