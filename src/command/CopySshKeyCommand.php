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

		$instances = TrimHelper::getInstances();
		$instancesInfo = TrimHelper::getInstancesInfo($instances);
		if (isset($instancesInfo)) {
			$io->newLine();
			$renderResult = TrimHelper::renderInstancesTable($output, $instancesInfo);

			$io->newLine();
			$output->writeln('<comment>In case you want to copy the SSH key to more than one instance, please use a comma (,) between the values</comment>');

			$helper = $this->getHelper('question');
			$question = TrimHelper::getQuestion('Which instance(s) do you want to copy the SSH key', null, '?');
			$question->setValidator(function ($answer) use ($instances) {
				return TrimHelper::validateInstanceSelection($answer, $instances);
			});

			$selectedInstances = $helper->ask($input, $output, $question);
			foreach ($selectedInstances as $instance) {
				$output->writeln('<fg=cyan>Copying SSH key to ' . $instance->name . '... (use "exit" to move to next the instance)</>');
				$access = $instance->getBestAccess('scripting');
				$access->firstConnect();
			}
		} else {
			$output->writeln('<comment>No instances available to copy the SSH key.</comment>');
		}
	}
}