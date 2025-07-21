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
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Hooks\InstanceProfileApplyHook;
use TikiManager\Libs\Helpers\Archive;

class ApplyProfileCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:profile:apply')
            ->setDescription('Apply profile to instance')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs (or names) to be deleted, separated by comma (,). You can also use the "all" keyword.'
            )
            ->addOption(
                'repository',
                'r',
                InputOption::VALUE_REQUIRED,
                'Tiki profiles repository. Default: \'profiles.tiki.org\'',
                'profiles.tiki.org'
            )
            ->addOption(
                'profile',
                'p',
                InputOption::VALUE_REQUIRED,
                'Tiki profiles repository. Default: \'profiles.tiki.org\''
            )
            ->addOption(
                'validate',
                null,
                InputOption::VALUE_NONE,
                'Attempt to validate the instance by checking its URL.'
            )
            ->setHelp('This command allows you to apply a profile to an instance');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances('update');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (empty($input->getOption('instances'))) {
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $this->io->newLine();
            $output->writeln('<comment>Note: Only Tiki instances can have profiles applied</comment>');
            $this->io->newLine();
            $output->writeln('<comment>In case you want to apply the profile to more than one instance, please use a comma (,) between the values</comment>');
            $answer = $this->io->ask('Which instance(s) do you want to apply the profile on', null, function ($answer) use ($instances) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                return implode(',', CommandHelper::getInstanceIds($selectedInstances));
            });

            $input->setOption('instances', $answer);
        }

        if (empty($input->getOption('profile'))) {
            $profile = $this->io->ask('What is the name of the profile to be applied?', null, function ($answer) {
                if (empty($answer)) {
                    throw new \RuntimeException('Profile name cannot be empty');
                }
                return $answer;
            });
            $input->setOption('profile', $profile);

            $repository = $this->io->ask('Which repository do what do use?', 'profiles.tiki.org');
            $input->setOption('repository', $repository);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $instances = CommandHelper::getInstances('tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        $repository = $input->getOption('repository');
        $profile = $input->getOption('profile');

        if (!isset($instancesInfo)) {
            throw new \RuntimeException('No Tiki instances available to apply a profile.');
        }

        if (empty($profile)) {
            throw new \RuntimeException('Profile name cannot be empty.');
        }

        $selectedInstances = CommandHelper::validateInstanceSelection($input->getOption('instances'), $instances);

        $hookName = $this->getCommandHook();
        $instanceProfileApplyHook = new InstanceProfileApplyHook($hookName->getHookName(), $this->logger);

        foreach ($selectedInstances as $instance) {
            $this->io->writeln(sprintf('<fg=cyan>Applying profile to %s ...</>', $instance->name));
            $instance->getApplication()->installProfile($repository, $profile);
            Archive::cleanup($instance->id, $instance->name);

            $hookName->registerPostHookVars(['instance' => $instance]);
        }

        $hookName->registerPostHookVars(['profile' => $profile]);

        if ($input->getOption('validate')) {
            CommandHelper::validateInstances($selectedInstances, $instanceProfileApplyHook);
        }

        return 0;
    }
}
