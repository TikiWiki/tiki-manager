<?php

// src/Command/CreateUserCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\ChoiceQuestion;

class CreateInstanceCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('instance:create')
			->setDescription('Creates a new instance')
			->setHelp('This command allows you to create a new instance')
			->addArgument('blank', InputArgument::OPTIONAL);

	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$blank = $input->getArgument('blank') == 'blank' ? true : false;

		$output->writeln('<comment>Answer the following to add a new TRIM instance.</comment>');

		$helper = $this->getHelper('question');
		$question = new ChoiceQuestion(
			'Connection type:',
			array('ftp', 'local', 'ssh'), null
		);
		$question->setErrorMessage('Connection type %s is invalid.');
		$type = $helper->ask($input, $output, $question);


		if ($type != 'local') {

			$question = TrimHelper::getQuestion('Host name');
			$host = $helper->ask($input, $output, $question);

			$question = TrimHelper::getQuestion('Port number', ($type == 'ssh') ? 22 : 21);
			$port = $helper->ask($input, $output, $question);

			$question = TrimHelper::getQuestion('User');
			$user = $helper->ask($input, $output, $question);

			$question = TrimHelper::getQuestion('Password');
			$question->setHidden(true);
			$question->setHiddenFallback(false);

			while ($type == 'ftp' && empty($pass)) {
				$pass = $helper->ask($input, $output, $question);
			}

			$d_name = $host;

		} else {
			if ($type == 'local') {

				if (function_exists('posix_getpwuid')) {
					$user = posix_getpwuid(posix_geteuid())['name'];
				} elseif (!empty($_SERVER['USER'])) {
					$user = $_SERVER['USER'];
				} else {
					$user = '';
				}
				$pass = '';
				$host = 'localhost';
				$port = 0;
				$d_name = 'localhost';
			}
		}

		$name = $contact = $webroot = $tempdir = $weburl = '';

		$question = TrimHelper::getQuestion('Instance name', $d_name);
		$name = $helper->ask($input, $output, $question);

		$question = TrimHelper::getQuestion('Contact email');
		$contact = $helper->ask($input, $output, $question);

		$instance = new \Instance();
		$instance->name = $name;
		$instance->contact = $contact;
		$instance->webroot = rtrim($webroot, '/');
		$instance->weburl = rtrim($weburl, '/');
		$instance->tempdir = rtrim($tempdir, '/');

		if ($type == 'ftp') {
			$question = TrimHelper::getQuestion('Web root', "/home/$user/public_html");
			$webroot = $helper->ask($input, $output, $question);

			$question = TrimHelper::getQuestion('Web URL', "http://$host");
			$webroot = $helper->ask($input, $output, $question);

			$instance->webroot = rtrim($webroot, '/');
			$instance->weburl = rtrim($weburl, '/');
		}

		$instance->save();

		$output->writeln('<info>Instance information saved.</info>');

		$access = $instance->registerAccessMethod($type, $host, $user, $pass, $port);

		if (!$access) {
			$instance->delete();
			$output->writeln('<error>Set-up failure. Instance removed.</error>');
		}

		$output->writeln('<info>Detecting remote configuration.</info>');
		if (!$instance->detectSVN()) {
			$output->writeln('<error>Subversion not detected on the remote server</error>');
			exit(-1);
		}

		$d_linux = $instance->detectDistribution();
		$output->writeln('<info>You are running : ' . $d_linux . '</info>');

		switch ($d_linux) {
			case "ClearOS":
				$backup_user = @posix_getpwuid(posix_geteuid())['name'];
				$backup_group = 'allusers';
				$backup_perm = 02770;
				$host = preg_replace("/[\\\\\/?%*:|\"<>]+/", '-', $instance->name);
				$d_webroot = ($user == 'root' || $user == 'apache') ?
					"/var/www/virtual/{$host}/html/" : "/home/$user/public_html/";
				break;
			default:
				$backup_user = @posix_getpwuid(posix_geteuid())['name'];
				$backup_group = @posix_getgrgid(posix_getegid())['name'];
				$backup_perm = 02750;
				$d_webroot = ($user == 'root' || $user == 'apache') ?
					'/var/www/html/' : "/home/$user/public_html/";
		}

		if ($type != 'ftp') {
			$question = TrimHelper::getQuestion('Web root', $d_webroot);
			$webroot = $helper->ask($input, $output, $question);

			$question = TrimHelper::getQuestion('Web URL', "http://$host");
			$weburl = $helper->ask($input, $output, $question);
		}

		$question = TrimHelper::getQuestion('Working directory', TRIM_TEMP);
		$tempdir = $helper->ask($input, $output, $question);

		if ($access instanceof ShellPrompt) {
			$access->shellExec("mkdir -p $tempdir");
		} else {
			$output->writeln('<error>Shell access is required to create the working directory. You will need to create it manually.</error>');
			exit (-1);
		}

		$question = TrimHelper::getQuestion('Backup owner', $backup_user);
		$backup_user = $helper->ask($input, $output, $question);

		$question = TrimHelper::getQuestion('Backup group', $backup_group);
		$backup_group = $helper->ask($input, $output, $question);

		$question = TrimHelper::getQuestion('Backup file permissions', decoct($backup_perm));
		$backup_perm = $helper->ask($input, $output, $question);

		$instance->weburl = rtrim($weburl, '/');
		$instance->webroot = rtrim($webroot, '/');
		$instance->tempdir = rtrim($tempdir, '/');

		$instance->backup_user = trim($backup_user);
		$instance->backup_group = trim($backup_group);
		$instance->backup_perm = octdec($backup_perm);

		if (!$instance->detectPHP()) {
			$output->writeln('<error>PHP Interpreter could not be found on remote host.</error>');
			exit(-1);
		} else {
			if ($instance->phpversion < 50300) {
				$output->writeln('<error>PHP Interpreter version is less than 5.3.</error>');
				die(-1);
			}
		}

		$instance->save();
		$output->writeln('<info>Instance information saved.</info>');

		if ($blank) {
			$output->writeln('<fg=blue>This is a blank (empty) instance. This is useful to restore a backup later.</>');
		} else {
			perform_instance_installation($instance);
			$output->writeln('<fg=blue>Please test your site at ' . $instance->weburl . '</>');
		}
	}
}
