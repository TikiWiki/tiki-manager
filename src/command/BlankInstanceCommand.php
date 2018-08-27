<?php

namespace App\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BlankInstanceCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('instance:blank')
			->setDescription('Creates a new blank instance')
			->setHelp('This command allows you to create a new blank instance without actually add a Tiki');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$command = $this->getApplication()->find('instance:create');

		$arguments = array(
			'command' => 'instance:create',
			'blank'    => 'blank'
		);

		$blankInstanceInput = new ArrayInput($arguments);
		$returnCode = $command->run($blankInstanceInput, $output);
	}
}