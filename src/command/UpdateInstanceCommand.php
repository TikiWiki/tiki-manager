<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;

class UpdateInstanceCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('instance:update')
			->setDescription('Update instance')
			->setHelp('This command allows you update an instance')
			->addArgument('mode', InputArgument::IS_ARRAY | InputArgument::OPTIONAL);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$instances = TrimHelper::getInstances('update');
		$instancesInfo = TrimHelper::getInstancesInfo($instances);
		if (isset($instancesInfo)) {
			$helper = $this->getHelper('question');

			$switch = false;
			$auto = false;

			$argument = $input->getArgument('mode');
			if (isset($argument) && ! empty($argument)) {
				if (is_array($argument)) {
					$switch = $input->getArgument('mode')[0] == 'switch' ? true : false;
					$auto = $input->getArgument('mode')[0] == 'auto' ? true : false;
				} else {
					$switch = $input->getArgument('mode') == 'switch' ? true : false;
				}
			}

			if ($auto) {
				$instancesIds = array_slice($input->getArgument('mode'), 1 );

				$selectedInstances = array();
				foreach ($instancesIds as $index) {
					if (array_key_exists($index, $instances))
						$selectedInstances[] = $instances[$index];
				}
			} else {
				$io = new SymfonyStyle($input, $output);
				$output->writeln('<comment>WARNING: Only SVN instances can be updated.</comment>');

				$io->newLine();
				$renderResult = TrimHelper::renderInstancesTable($output, $instancesInfo);

				$io->newLine();
				$output->writeln('<comment>In case you want to update more than one instance, please use a comma (,) between the values</comment>');

				$question = TrimHelper::getQuestion('Which instance(s) do you want to update', null, '?');
				$question->setValidator(function ($answer) use ($instances) {
					return TrimHelper::validateInstanceSelection($answer, $instances);
				});

				$selectedInstances = $helper->ask($input, $output, $question);
			}

			foreach ($selectedInstances as $instance) {
				$output->writeln('<fg=cyan>Working on ' . $instance->name . '</>');

				$locked = $instance->lock();
				$instance->detectPHP();
				$app = $instance->getApplication();

				if (!$app->isInstalled()) {
					ob_start();
					perform_instance_installation($instance);
					$contents = $string = trim(preg_replace('/\s\s+/', ' ', ob_get_contents()));
					ob_end_clean();

					$matches = array();
					if(preg_match('/(\d+\.|trunk)/', $contents, $matches)) {
						$branch_name = $matches[0];
					}
				}

				$version = $instance->getLatestVersion();
				$branch_name = $version->getBranch();
				$branch_version = $version->getBaseVersion();

				if ($switch) {
					$versions = array();
					$versions_raw = $app->getVersions();
					foreach ($versions_raw as $version) {
						if ($version->type == 'svn')
							$versions[] = $version;
					}

					$output->writeln('<fg=cyan>You are currently running: ' . $branch_name . '</>');

					$counter = 0;
					$found_incompatibilities = false;
					foreach ($versions as $key => $version) {
						$base_version = $version->getBaseVersion();

						$compatible = 0;
						$compatible |= $base_version >= 13;
						$compatible &= $base_version >= $branch_version;
						$compatible |= $base_version === 'trunk';
						$compatible &= $instance->phpversion > 50500;
						$found_incompatibilities |= !$compatible;

						if ($compatible) {
							$counter++;
							$output->writeln('[' . $key .'] ' . $version->type . ' : ' . $version->branch);
						}
					}

					if ($counter) {
						$question = TrimHelper::getQuestion('Which version do you want to upgrade to', null, '?');
						$selectedVersion = $helper->ask($input, $output, $question);
						$versionSel = getEntries($versions, $selectedVersion);

						if (empty($versionSel) && ! empty($input)) {
							$target = \Version::buildFake('svn', $input);
						} else {
							$target = reset($versionSel);
						}

						if (count($versionSel) > 0) {
							$filesToResolve = $app->performUpdate($instance, $target);
							$version = $instance->getLatestVersion();
							handleCheckResult($instance, $version, $filesToResolve);
						} else {
							$output->writeln('<comment>No version selected. Nothing to perform.</comment>');
						}
					} else {
						$output->writeln('<comment>No upgrades are available. This is likely because you are already at</comment>');
						$output->writeln('<comment>the latest version permitted by the server.</comment>');
					}
				} else {
					$filesToResolve = $app->performUpdate($instance);
					$version = $instance->getLatestVersion();
					handleCheckResult($instance, $version, $filesToResolve);
				}

				if ($locked) $instance->unlock();
			}
		} else {
			$output->writeln('<comment>No instances available to update/upgrade.</comment>');
		}
	}
}