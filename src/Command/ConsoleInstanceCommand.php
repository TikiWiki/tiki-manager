<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
            ->setHelp('This command allows you to run any console command from Tiki');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances('tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $output->writeln('<comment>Note: Only Tiki instances can run Console commands.</comment>');

            $io->newLine();
            $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

            $io->newLine();
            $output->writeln('<comment>In case you want to run Console commands in more than one instance, please use a comma (,) between the values</comment>');
            $output->writeln('<comment>Note: If you write \'help\' you can check the list of commands</comment>');

            $helper = $this->getHelper('question');
            $question = CommandHelper::getQuestion('Which instance(s) do you want run Console commands', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);

            $question = CommandHelper::getQuestion('Write command to execute', null);
            $question->setNormalizer(function ($value) {
                return $value == 'help' ? '' : $value;
            });
            $command = $helper->ask($input, $output, $question);

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
        } else {
            $output->writeln('<comment>No Tiki instances available to run Console commands.</comment>');
        }
    }
}
