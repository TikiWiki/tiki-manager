<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class EditInstanceCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('instance:edit')
			->setDescription('Edit instance')
			->setHelp('This command allows you to modify an instance which is managed by TRIM');
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
			$output->writeln('<comment>In case you want to edit more than one instance, please use a comma (,) between the values</comment>');

			$helper = $this->getHelper('question');
			$question = TrimHelper::getQuestion('Which instance(s) do you want to edit', null ,'?');
			$question->setValidator(function ($answer) {
				return TrimHelper::validateInstanceSelection($answer);
			});

			$selectedInstances = $helper->ask($input, $output, $question);
			foreach ($selectedInstances as $instance) {
				$output->writeln('<fg=cyan>Edit data for ' . $instance->name . '...</>');

				$result = query(SQL_SELECT_ACCESS, array(':id' => $instance->id));
				$instanceType = $result->fetch()['type'];

				if ($instanceType != 'local') {
					$question = TrimHelper::getQuestion('Host name', $instance->name);
				} else if ($instanceType == 'local') {
					$question = TrimHelper::getQuestion('Instance name', $instance->name);

				}
				$name = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Contact email', $instance->contact);
				$contact = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Web root', $instance->webroot);
				$webroot = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Web URL', $instance->weburl);
				$weburl = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Working directory', $instance->tempdir);
				$tempdir = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Backup owner', $instance->getProp('backup_user'));
				$backup_user = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Backup group', $instance->getProp('backup_group'));
				$backup_group = $helper->ask($input, $output, $question);

				$backup_perm = intval($instance->getProp('backup_perm') ?: 0775);
				$question = TrimHelper::getQuestion('Backup file permissions', decoct($backup_perm));
				$backup_perm = $helper->ask($input, $output, $question);

				$instance->name = $name;
				$instance->contact = $contact;
				$instance->webroot = rtrim($webroot, '/');
				$instance->weburl = rtrim($weburl, '/');
				$instance->tempdir = rtrim($tempdir, '/');
				$instance->backup_user = $backup_user;
				$instance->backup_group = $backup_group;
				$instance->backup_perm = octdec($backup_perm);
				$instance->save();

				$output->writeln('<info>Instance information saved.</info>');
			}
		} else {
			$output->writeln('<comment>No Tiki instances available to edit.</comment>');
		}
	}
}