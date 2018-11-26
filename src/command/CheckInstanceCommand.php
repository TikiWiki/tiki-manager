<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;

class CheckInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:check')
            ->setDescription('Check instance')
            ->setHelp('This command allows you to check an instance');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = TrimHelper::getInstances('all', true);
        $instancesInfo = TrimHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $io->newLine();
            $renderResult = TrimHelper::renderInstancesTable($output, $instancesInfo);

            $io->newLine();
            $output->writeln('<comment>In case you want to check more than one instance, please use a comma (,) between the values</comment>');

            $helper = $this->getHelper('question');
            $question = TrimHelper::getQuestion('Which instance(s) do you want to check', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return TrimHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
            foreach ($selectedInstances as $instance) {
                $version = $instance->getLatestVersion();

                if (! $version) {
                    $output->writeln('<comment>Instance [' . $instance->id . '] (' . $instance->name . ') does not have a registered version. Skip.</comment>');
                    continue;
                }

                $output->writeln('<fg=cyan>Checking instance: ' . $instance->name . '...</>');

                if ($version->hasChecksums()) {
                    handleCheckResult($instance, $version, $version->performCheck($instance));
                } else {
                    $output->writeln('<comment>No checksums exist.</comment>');
                    $io->newLine();
                    TrimHelper::renderCheckOptionsAndActions($output);
                    $io->newLine();

                    $question = new ChoiceQuestion(
                        'Please select an option to apply:',
                        ['current', 'source', 'skip'],
                        null
                    );
                    $question->setErrorMessage('Option %s is invalid.');
                    $option = $helper->ask($input, $output, $question);

                    if ($option == 'skip') {
                        continue;
                    }

                    switch ($option) {
                        case 'source':
                            $version->collectChecksumFromSource($instance);
                            handleCheckResult($instance, $version, $version->performCheck($instance));
                            break;
                        case 'current':
                            $version->collectChecksumFromInstance($instance);
                            break;
                    }
                }
            }
        } else {
            $output->writeln('<comment>No instances available to check.</comment>');
        }
    }
}
