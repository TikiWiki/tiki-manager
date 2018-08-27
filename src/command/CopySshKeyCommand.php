<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CopySshKeyCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('instance:copysshkey')
			->setDescription('Copy SSH key')
			->setHelp('This command allows you copy the SSH key to the remote instance');
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
			$output->writeln('<comment>In case you want to copy the SSH key to more than one instance, please use a comma (,) between the values</comment>');

			$helper = $this->getHelper('question');
			$question = TrimHelper::getQuestion('Which instance(s) do you want to copy the SSH key', null, '?');
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
				return $instancesId;
			});

			$instancesId = $helper->ask($input, $output, $question);
			foreach ($instancesId as $id) {
				$output->writeln('<fg=cyan>Copying SSH key to ' . $instances[$id]->name . '... (use "exit" to move to next the instance)</>');
				$access = $instances[$id]->getBestAccess('scripting');
				$access->firstConnect();
			}
		} else {
			$output->writeln('<comment>No instances available to copy the SSH key.</comment>');
		}
	}
}