<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Application\Instance;
use TikiManager\Application\Tiki\Versions\Fetcher\YamlFetcher;
use TikiManager\Application\Tiki\Versions\TikiRequirementsHelper;
use TikiManager\Command\Helper\CommandHelper;

class DetectInstanceCommand extends TikiManagerCommand
{
    protected $instances;
    protected $instancesInfo;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:detect')
            ->setDescription('Detect Tiki branch or tag')
            ->setHelp('This command allows you to detect a Tiki branch or tag, for debugging purpose')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be detected, separated by comma (,)'
            )
            ->addOption(
                'update-branch',
                null,
                InputOption::VALUE_OPTIONAL,
                'Update branch name in case Tiki Application branch is different than the one stored in the Tiki Manager db. Default is false',
                false
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        $this->instances = $instances;
        $this->instancesInfo = $instancesInfo;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('instances'))) {
            if (empty($this->instancesInfo)) {
                return;
            }

            CommandHelper::renderInstancesTable($output, $this->instancesInfo);
            $answer = $this->io->ask('Which instance(s) do you want to detect', null, function ($answer) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $this->instances);
                return implode(',', array_map(function ($elem) {
                    return $elem->getId();
                }, $selectedInstances));
            });

            $input->setOption('instances', $answer);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (empty($this->instancesInfo)) {
            $output->writeln('<comment>No instances available to detect.</comment>');
            return;
        }

        $instancesOption = $input->getOption('instances');

        CommandHelper::validateInstanceSelection($instancesOption, $this->instances);
        $instancesOption = explode(',', $instancesOption);
        $selectedInstances = [];
        foreach ($instancesOption as $key) { // keeping the same order as in $instancesOption
            if (array_key_exists($key, $this->instances)) {
                $selectedInstances[$key] = $this->instances[$key];
            }
        }
        $hookName = $this->getCommandHook();

        /** @var Instance $instance */
        foreach ($selectedInstances as $instance) {
            if ($instance->name) {
                $this->io->section($instance->name);
            }
            $originalExec = $instance->phpexec;

            $instance->phpexec = null;
            $instance->phpversion = null;
            if (! $instance->detectPHP()) {
                if ($instance->phpversion < 50300) {
                    $this->io->error('PHP Interpreter version is less than 5.3.');
                    continue;
                } else {
                    $this->io->error('PHP Interpreter could not be found on remote host.');
                    continue;
                }
            }

            if ($originalExec && $instance->phpexec && $originalExec != $instance->phpexec) {
                $instance->save();
            }

            $phpVersion = CommandHelper::formatPhpVersion($instance->phpversion);
            $this->io->writeln('<info>Instance PHP Version: ' . $phpVersion . '</info>');

            // Redetect the VCS type
            $instance->vcs_type = $instance->getDiscovery()->detectVcsType();
            $instance->save();

            $app = $instance->getApplication();

            if (!$app) {
                $this->io->writeln('<info>Blank instance detected. Skipping...</info>');
                continue;
            }

            $updateBranch = $input->getOption('update-branch');
            $branch = $app->getBranch();
            if ($instance->branch != $branch) {
                if (!$updateBranch) {
                    $this->io->error('Tiki Application branch is different than the one stored in the Tiki Manager db.');
                    continue;
                }

                $this->io->warning('Tiki Application branch is different than the one stored in the Tiki Manager db, updating tiki-manager data.');
                $instance->setBranchAndRepo($branch, $instance->repo_url);
                $instance->updateVersion();
            }

            $requirements_helper = new TikiRequirementsHelper(new YamlFetcher());
            $instanceBranch = $instance->getBranch();
            $tikiRequirements = $requirements_helper->findByBranchName($instanceBranch);

            if ($tikiRequirements->checkRequirements($instance)) {
                $this->io->writeln('<info>PHP version is supported.</info>');
            } elseif ($instanceBranch === 'master' && $tikiRequirements->checkRequirements($instance, true)) {
                $maxPhpVersion = CommandHelper::formatPhpVersion($tikiRequirements->getPhpVersion()->getMax());
                $this->io->warning("This version of PHP ($phpVersion) is above the recommended max version ($maxPhpVersion) for the master branch and Tiki may not work as expected.");
            } else {
                $this->io->error('PHP version is not supported.');
                continue;
            }

            $hookName->registerPostHookVars(['instance' => $instance, 'branch' => $branch]);

            $this->io->writeln('<info>Detected ' .strtoupper($instance->vcs_type) . ': ' . $branch . '</info>');
        }

        return 0;
    }
}
