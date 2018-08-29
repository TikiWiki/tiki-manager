<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DetectInstanceCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('instance:detect')
			->setDescription('Detect Tiki branch or tag')
			->setHelp('This command allows you to detect a Tiki branch or tag, for debugging purpose');
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
			$output->writeln('<comment>In case you want to detect more than one instance, please use a comma (,) between the values</comment>');

			$helper = $this->getHelper('question');
			$question = TrimHelper::getQuestion('Which instance(s) do you want to detect', null, '?');
			$question->setValidator(function ($answer) {
				return TrimHelper::validateInstanceSelection($answer);
			});

			$instancesId = $helper->ask($input, $output, $question);
			foreach ($instancesId as $id) {
				if (! $instances[$id]->detectPHP()) {
					if ($instances[$id]->phpversion < 50300) {
						$output->writeln('<error>PHP Interpreter version is less than 5.3.</error>');
						die(-1);
					} else {
						$output->writeln('<error>PHP Interpreter could not be found on remote host.</error>');
						die(-1);
					}
				}

				perform_instance_installation($instances[$id]);

				$matches = array();
				preg_match('/(\d+)(\d{2})(\d{2})$/',
					$instances[$id]->phpversion, $matches);

				if (count($matches) == 4) {
					info(sprintf("Detected PHP : %d.%d.%d",
						$matches[1], $matches[2], $matches[3]));
				}
			}
		} else {
			$output->writeln('<comment>No instances available to detect.</comment>');
		}
	}
}