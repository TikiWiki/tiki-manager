<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WatchInstanceCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('instance:watch')
			->setDescription('Set-up cron job to perform an hash check')
			->setHelp('This command allows you to set-up a cron job on the TRIM master to perform the Hash check automatically every day.');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);

		$helper = $this->getHelper('question');
		$question = TrimHelper::getQuestion('Email address to contact');
		$question->setValidator(function ($value) {
			if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
				throw new \Exception('Please insert a valid email address');
			}
			return $value;
		});
		$email = $helper->ask($input, $output, $question);

		$question = TrimHelper::getQuestion('What time should it run at?','00:00');
		$question->setValidator(function ($value) {
			$match = preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/', $value);
			if (! $match) {
				throw new \Exception('Please insert a valid time value');
			}
			return $value;
		});
		$time = $helper->ask($input, $output, $question);

		list($hour, $minute) = explode(':', $time);
		$hour = (int)$hour;
		$minute = (int)$minute;

		$path = realpath(dirname(__FILE__) . '/../../scripts/watch.php');
		$entry = sprintf(
			"%d %d * * * %s -d memory_limit=256M %s %s\n",
			$minute, $hour, php(), $path, $email);

		file_put_contents($file = TEMP_FOLDER . '/crontab', `crontab -l` . $entry);

		$io->newLine();
		$output->writeln('<comment>If adding to crontab fails and blocks, hit Ctrl-C and add these parameters manually.</comment>');
		$output->writeln($entry);

		`crontab $file`;
	}
}