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

			$instancesId = $helper->ask($input, $output, $question);
			foreach ($instancesId as $id) {
				$output->writeln('<fg=cyan>Edit data for ' . $instances[$id]->name . '...</>');

				$result = query(SQL_SELECT_ACCESS, array(':id' => $instances[$id]->id));
				$instanceType = $result->fetch()['type'];

				if ($instanceType != 'local') {
					$question = TrimHelper::getQuestion('Host name', $instances[$id]->name);
				} else if ($instanceType == 'local') {
					$question = TrimHelper::getQuestion('Instance name', $instances[$id]->name);

				}
				$name = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Contact email', $instances[$id]->contact);
				$contact = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Web root', $instances[$id]->webroot);
				$webroot = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Web URL', $instances[$id]->weburl);
				$weburl = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Working directory', $instances[$id]->tempdir);
				$tempdir = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Backup owner', $instances[$id]->getProp('backup_user'));
				$backup_user = $helper->ask($input, $output, $question);

				$question = TrimHelper::getQuestion('Backup group', $instances[$id]->getProp('backup_group'));
				$backup_group = $helper->ask($input, $output, $question);

				$backup_perm = intval($instances[$id]->getProp('backup_perm') ?: 0775);
				$question = TrimHelper::getQuestion('Backup file permissions', decoct($backup_perm));
				$backup_perm = $helper->ask($input, $output, $question);

				$instances[$id]->name = $name;
				$instances[$id]->contact = $contact;
				$instances[$id]->webroot = rtrim($webroot, '/');
				$instances[$id]->weburl = rtrim($weburl, '/');
				$instances[$id]->tempdir = rtrim($tempdir, '/');
				$instances[$id]->backup_user = $backup_user;
				$instances[$id]->backup_group = $backup_group;
				$instances[$id]->backup_perm = octdec($backup_perm);
				$instances[$id]->save();

				$output->writeln('<info>Instance information saved.</info>');
			}
		} else {
			$output->writeln('<comment>No Tiki instances available to edit.</comment>');
		}
	}
}