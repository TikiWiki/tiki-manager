<?php

namespace App\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpgradeInstanceCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('instance:upgrade')
			->setDescription('Upgrade instance')
			->setHelp('This command allows you to upgrade an instance');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$command = $this->getApplication()->find('instance:update');

		$arguments = array(
			'command' => 'instance:update',
			'mode'    => 'switch'
		);

		$verifyInstanceInput = new ArrayInput($arguments);
		$returnCode = $command->run($verifyInstanceInput, $output);
	}
}