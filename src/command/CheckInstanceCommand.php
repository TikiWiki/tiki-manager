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

		$instances = TrimHelper::getInstances();
		$instancesInfo = TrimHelper::getInstancesInfo($instances);
		if (isset($instancesInfo)) {
			$io->newLine();
			$renderResult = TrimHelper::renderInstancesTable($output, $instancesInfo);

			$io->newLine();
			$output->writeln('<comment>In case you want to check more than one instance, please use a comma (,) between the values</comment>');

			$helper = $this->getHelper('question');
			$question = TrimHelper::getQuestion('Which instance(s) do you want to check', null, '?');
			$question->setValidator(function ($answer) {
				return TrimHelper::validateInstanceSelection($answer);
			});

			$instancesId = $helper->ask($input, $output, $question);
			foreach ($instancesId as $id) {
				$version = $instances[$id]->getLatestVersion();

				if (! $version) {
					$output->writeln('<comment>Instance [' . $instances[$id]->id . '] (' . $instances[$id]->name . ') does not have a registered version. Skip.</comment>');
					continue;
				}

				$output->writeln('<fg=cyan>Checking instance: ' . $instances[$id]->name . '...</>');

				if ($version->hasChecksums())
					handleCheckResult($instances[$id], $version, $version->performCheck($instances[$id]));
				else {
					$output->writeln('<comment>No checksums exist.</comment>');
					$io->newLine();
					TrimHelper::renderCheckOptionsAndActions($output);
					$io->newLine();

					$question = new ChoiceQuestion(
						'Please select an option to apply:',
						array('current', 'source', 'skip'), null
					);
					$question->setErrorMessage('Option %s is invalid.');
					$option = $helper->ask($input, $output, $question);

					switch ($option) {
						case 'source':
							$version->collectChecksumFromSource($instances[$id]);
							handleCheckResult($instances[$id], $version, $version->performCheck($instances[$id]));
							break;
						case 'current':
							$version->collectChecksumFromInstance($instances[$id]);
							break;
						case 'skip':
							continue;
					}
				}
			}
		} else {
			$output->writeln('<comment>No instances available to check.</comment>');
		}
	}
}