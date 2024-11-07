<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;

class MaintenanceInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:maintenance')
            ->setDescription('instances under maintenance')
            ->setHelp('This command allows you to put instances under maintenance or live mode')
            ->addArgument('status', InputArgument::REQUIRED)
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'Instances id\'s'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $status = $input->getArgument('status');
        $instancesOption = $input->getOption('instances');
        $instancesOption = ! empty($instancesOption) ? explode(',', $instancesOption) : [];

        if (! in_array($status, ['on', 'off'])) {
            $this->io->error('Please insert a valid status [on, off].');
            return Command::FAILURE;
        }

        $instances = CommandHelper::getInstances('all', true);

        $validInstances = [];
        foreach ($instances as $instance) {
            array_push($validInstances, $instance->id);
        }

        $validInstancesOptions = count(array_intersect($instancesOption, $validInstances)) == count($instancesOption);
        if (! $validInstancesOptions) {
            $this->io->error('Please insert a valid instance id.');
            return Command::FAILURE;
        }

        $result = Command::FAILURE;;
        $messages = [];
        $errors = [];

        $hookName = $this->getCommandHook();
        if (! empty($instancesOption)) {
            foreach ($instancesOption as $instanceId) {
                $instance = Instance::getInstance($instanceId);
                $success = ($status == 'on') ? $instance->lock() : $instance->unlock();
                if ($success) {
                    $messages[] = $instance->name;
                } else {
                    $errors[] = $instance->name;
                }
                $instance->getApplication()->fixPermissions();
                $hookName->registerPostHookVars(['instance' => $instance, 'maintenance_status' => $instance->isLocked() ? 'on' : 'off']);
            }
            if (! empty($messages)) {
                $this->io->success('Instances [' . implode(',', $messages) . '] maintenance "' . $status . '"');
                $result = 0;
            }
            if (! empty($errors)) {
                $this->io->error('Instances [' . implode(',', $errors) . '] change maintenance "' . $status . '" failed');
                $result = 1;
            }
        } else {
            $instancesInfo = CommandHelper::getInstancesInfo($instances);
            if (isset($instancesInfo)) {
                CommandHelper::renderInstancesTable($output, $instancesInfo);

                $question = CommandHelper::getQuestion('Select the instance', null);
                $question->setValidator(function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                });

                $selectedInstance = $helper->ask($input, $output, $question);
                $instance = $selectedInstance[0];
                $success = ($status == 'on') ? $instance->lock() : $instance->unlock();
                $instance->getApplication()->fixPermissions();
                $hookName->registerPostHookVars(['instance' => $instance, 'maintenance_status' => $instance->isLocked() ? 'on' : 'off']);

                if ($success) {
                    $this->io->success('Instance ' . $instance->name . ' maintenance ' . $status);
                    $result = Command::SUCCESS;
                } else {
                    $this->io->error('Instance ' . $instance->name . ' maintenance ' . $status);
                }
            }
        }

        return $result;
    }
}
