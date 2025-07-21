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
                'List of instance IDs (or names), separated by comma (,). You can also use the "all" keyword.'
            )
            ->addOption(
                'copy-errors',
                null,
                InputOption::VALUE_OPTIONAL,
                'Handle rsync errors: use "stop" to halt on errors or "ignore" to proceed despite errors'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (empty($input->getOption('instances'))) {
            $instances = CommandHelper::getInstances('all', true);
            $instancesInfo = CommandHelper::getInstancesInfo($instances);

            if (empty($instancesInfo)) {
                // Let the execute function handle this case.
                return;
            }

            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $answer = $this->io->ask('Select the instance(s)', null, function ($answer) use ($instances) {
                $selectedInstances = CommandHelper::validateInstanceSelection($answer, $instances);
                return implode(',', CommandHelper::getInstanceIds($selectedInstances));
            });

            $input->setOption('instances', $answer);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = $input->getArgument('status');

        if (! in_array($status, ['on', 'off'])) {
            $this->io->error('Please insert a valid status [on, off].');
            return Command::FAILURE;
        }

        $instances = CommandHelper::getInstances('all', true);
        if (empty($instances)) {
            $output->writeln('<comment>No instance available.</comment>');
            return 0;
        }

        $instancesOption = $input->getOption('instances');
        $selectedInstances = CommandHelper::validateInstanceSelection($instancesOption, $instances);

        $hookName = $this->getCommandHook();
        $result = Command::SUCCESS; // will be changed to Command::FAILURE if at least on fails.
        foreach ($selectedInstances as $instance) {
            $success = ($status == 'on') ? $instance->lock() : $instance->unlock();
            $instance->copy_errors = $input->getOption('copy-errors') ?: 'ask';
            $instance->getApplication()->fixPermissions();
            $hookName->registerPostHookVars(['instance' => $instance, 'maintenance_status' => $instance->isLocked() ? 'on' : 'off']);
            if ($success) {
                $this->io->success('Instance ' . $instance->name . ' maintenance ' . $status);
            } else {
                $this->io->error('Instance ' . $instance->name . ' maintenance ' . $status);
                $result = Command::FAILURE;
            }
        }

        return $result;
    }
}
