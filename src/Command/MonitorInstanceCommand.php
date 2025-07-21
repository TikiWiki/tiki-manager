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
use Symfony\Component\Console\Helper\Table;

class MonitorInstanceCommand extends TikiManagerCommand
{
    protected $instances;

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('instance:monitor')
            ->setDescription('Monitor the status of the last operation for instances.')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Comma-separated (,) list of instance IDs or "all" for all instances'
            )
            ->addOption(
                'text-output',
                't',
                InputOption::VALUE_NONE,
                'Output in simple text format'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $instances = CommandHelper::getInstances();
        $this->instances = $instances;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $instanceIds = $input->getOption('instances');
        $simpleTextOutput = $input->getOption('text-output');

        if (empty($instanceIds) || $instanceIds === 'all') {
            $instances = $this->instances;
        } else {
            $instanceIds = explode(',', $instanceIds);
            $instances = array_intersect_key($this->instances, array_flip($instanceIds));
        }

        if (empty($instances)) {
            $output->writeln('<comment>Instances not available</comment>');
            return 0;
        }

        list($results, $hasFailures) = $this->monitorInstances($instances);

        if ($simpleTextOutput) {
            $this->outputFailureResults($output, $results, $hasFailures);
        } else {
            $this->renderMonitorInstancesTable($output, $results);
        }

        return $hasFailures ? 1 : 0;
    }

    protected function monitorInstances($instances)
    {
        $results = [];
        $hasFailures = false;

        foreach ($instances as $instance) {
            $operation = $instance->last_action ?? 'None';
            $status = $instance->state ?? 'unknown';

            $results[] = [
                'InstanceId' => $instance->getId(),
                'LastOperation' => $operation,
                'Result' => $status
            ];

            if ($status === 'failure') {
                $hasFailures = true;
            }
        }

        return [$results, $hasFailures];
    }

    private function outputFailureResults(OutputInterface $output, array $results, bool $hasFailures): void
    {
        if ($hasFailures) {
            $failures = array_filter($results, function ($result) {
                return $result['Result'] === 'failure';
            });

            $failureList = array_map(function ($result) {
                return $result['InstanceId'] . '-' . $result['LastOperation'];
            }, $failures);

            $output->writeln('Failures:' . implode(',', $failureList));
        } else {
            $output->writeln('No Failures');
        }
    }

    private function renderMonitorInstancesTable($output, $rows)
    {
        if (empty($rows)) {
            return false;
        }

        $instanceTableHeaders = [
            'InstanceId',
            'Last Operation',
            'Result'
        ];

        $table = new Table($output);
        $table
            ->setHeaders($instanceTableHeaders)
            ->setRows($rows);
        $table->render();
    }
}
