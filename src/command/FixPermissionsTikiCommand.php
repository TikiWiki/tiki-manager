<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


class FixPermissionsTikiCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('tiki:fixpermissions')
			->setDescription('Fix permission on a Tiki instance')
			->setHelp('This command allows you to fix permisions on a Tiki instance');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);

		$instances = \Instance::getTikiInstances();
		$instancesInfo = TrimHelper::getInstancesInfo($instances);
		if (isset($instancesInfo)) {
			$output->writeln('<comment>Note: Only Tiki instances can have permissions fixed.</comment>');

			$io->newLine();
			$renderResult = TrimHelper::renderInstancesTable($output, $instancesInfo);

			$io->newLine();
			$output->writeln('<comment>In case you want to fix permissions to more than one instance, please use a comma (,) between the values</comment>');

			$helper = $this->getHelper('question');
			$question = TrimHelper::getQuestion('Which instance(s) do you want to fix permissions', null ,'?');
			$question->setValidator(function ($answer) {
				if (empty($answer)) {
					throw new \RuntimeException(
						'You must select an #ID'
					);
				} else {
					$instances = \Instance::getTikiInstances();

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
				$output->writeln('<fg=cyan>Fixing permissions for ' . $instances[$id]->name . '...</>');
				$instances[$id]->getApplication()->fixPermissions();
			}
		} else {
			$output->writeln('<comment>No Tiki instances available to fix permissions.</comment>');
		}
	}
}