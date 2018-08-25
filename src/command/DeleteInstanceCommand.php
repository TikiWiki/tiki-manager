<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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

		$instances = \Instance::getInstances();
		$instancesInfo = TrimHelper::getInstancesInfo($instances);
		if (isset($instancesInfo)) {
			$io->newLine();
			$renderResult = TrimHelper::renderInstancesTable($output, $instancesInfo);
			$io->newLine();
			$output->writeln('<comment>This will NOT delete the software itself, just your instance connection to it</comment>');

			$helper = $this->getHelper('question');
			$question = TrimHelper::getQuestion('Which instance(s) do you want to delete?');
			$question->setValidator(function ($answer) {
				if (empty($answer)) {
					throw new \RuntimeException(
						'You must select an #ID'
					);
				} else {
					$instances = \Instance::getInstances();

					$instancesId = array_filter(array_map('trim', explode(',', $answer)));
					$invalidInstancesId = array_diff($instancesId, array_keys($instances));

					if ($invalidInstancesId) {
						throw new \RuntimeException(
							'Invalid instance(s) ID(s) #' . implode(',', $invalidInstancesId)
						);
					}
				}
				return $answer;
			});
			$answer = $helper->ask($input, $output, $question);

			$instancesId = array_filter(array_map('trim', explode(',', $answer)));
			foreach ($instancesId as $id) {
				$output->writeln('<fg=cyan>Deleting instance ' . $instances[$id]->name . ' ...</>');
				$instances[$id]->delete();
			}
		} else {
			$output->writeln('<comment>No instances available to delete.</comment>');
		}
	}
}