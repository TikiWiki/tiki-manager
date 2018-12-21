<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ApplyProfileCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('profile:apply')
            ->setDescription('Apply profile to instance')
            ->setHelp('This command allows you to apply a profile to an instance');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = TrimHelper::getInstances('tiki');
        $instancesInfo = TrimHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $output->writeln('<comment>Note: Only Tiki instances can have profiles applied</comment>');
            $io->newLine();

            $helper = $this->getHelper('question');
            $question = TrimHelper::getQuestion('Repository', 'profiles.tiki.org');
            $repository = $helper->ask($input, $output, $question);

            $question = TrimHelper::getQuestion('Profile');
            $question->setValidator(function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException(
                        'Profile name cannot be empty'
                    );
                }
                return $answer;
            });
            $profile = $helper->ask($input, $output, $question);

            $io->newLine();
            $renderResult = TrimHelper::renderInstancesTable($output, $instancesInfo);
            $io->newLine();
            $output->writeln('<comment>In case you want to apply the profile to more than one instance, please use a comma (,) between the values</comment>');

            $helper = $this->getHelper('question');
            $question = TrimHelper::getQuestion('Which instance(s) do you want to apply the profile on', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return TrimHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Applying profile to ' . $instance->name . '...</>');
                $instance->getApplication()->installProfile($repository, $profile);
                perform_archive_cleanup($instance->id, $instance->name);
            }
        } else {
            $output->writeln('<comment>No Tiki instances available to apply a profile.</comment>');
        }
    }
}
