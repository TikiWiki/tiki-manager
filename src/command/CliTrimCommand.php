<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CliTrimCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('trim:cli')
			->setDescription('Run Tiki console commands')
			->setHelp('This command allows you to run any console command from Tiki');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);

		$instances = TrimHelper::getInstances('tiki');
		$instancesInfo = TrimHelper::getInstancesInfo($instances);
		if (isset($instancesInfo)) {
			$output->writeln('<comment>Note: Only Tiki instances can run Console commands.</comment>');

			$io->newLine();
			$renderResult = TrimHelper::renderInstancesTable($output, $instancesInfo);

			$io->newLine();
			$output->writeln('<comment>In case you want to run Console commands in more than one instance, please use a comma (,) between the values</comment>');
			$output->writeln('<comment>Note: If you write \'help\' you can check the list of commands</comment>');

			$helper = $this->getHelper('question');
			$question = TrimHelper::getQuestion('Which instance(s) do you want run Console commands', null ,'?');
			$question->setValidator(function ($answer) {
				return TrimHelper::validateInstanceSelection($answer, 'tiki');
			});

			$selectedInstances = $helper->ask($input, $output, $question);

			$question = TrimHelper::getQuestion('Write command to execute', null);
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