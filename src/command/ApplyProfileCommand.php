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

		$instances = \Instance::getTikiInstances();
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
			$question->setValidator(function ($answer) {
				return TrimHelper::validateInstanceSelection($answer, 'tiki');
			});

			$instancesId = $helper->ask($input, $output, $question);
			foreach ($instancesId as $id) {
				$output->writeln('<fg=cyan>Applying profile to ' . $instances[$id]->name . '...</>');
				$instances[$id]->getApplication()->installProfile($repository, $profile);
				perform_archive_cleanup($instances[$id]->id, $instances[$id]->name);
			}
		} else {
			$output->writeln('<comment>No Tiki instances available to apply a profile.</comment>');
		}
	}
}