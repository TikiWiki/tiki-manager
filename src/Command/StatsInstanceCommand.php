<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;

class StatsInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:stats')
            ->setDescription('Fetch data (ex: KPIs) from instances.')
            ->setHelp('This command allows you to fetch data (ex: KPIs) from instances and push to a Tracker.')
            ->addOption(
                'instances',
                null,
                InputOption::VALUE_REQUIRED,
                'Instances to fetch KPI, separated by comma (,). If empty, data will be fetch from all instances.',
                'all'
            )
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'File name to output the stats. Required when --push-to is used.'
            )
            ->addOption(
                'push-to',
                'p',
                InputOption::VALUE_REQUIRED,
                'Instance to push collected instance stats'
            )
            ->addOption(
                'exclude',
                'e',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be excluded, separated by comma (,)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $currentCwd = getcwd();
        $instancesOpt = $input->getOption('instances');
        $excludesOpt = $input->getOption('exclude');
        $fileOpt = $input->getOption('file');
        $pushToOpt = $input->getOption('push-to');
        $header = ['id', 'instance_id', 'instance_name'];
        $data = [];
        $tmpFile = TEMP_FOLDER . DIRECTORY_SEPARATOR . 'kpi.csv';

        $sourceInstances = $this->getInstances($instancesOpt, $excludesOpt);
        $toInstance = null;

        if ($pushToOpt) {
            $toInstance = $this->getInstances($pushToOpt);
            $toInstance = $toInstance[0];

            if (empty($fileOpt)) {
                throw new \RuntimeException('Missing --file option required when using --push-to');
            }
        }

        foreach ($sourceInstances as $instance) {
            $io->writeln('<comment>Calling command in ' . $instance->name . '</comment>');
            $access = $instance->getBestAccess('scripting');

            $script = sprintf(
                '%s -q -d memory_limit=256M console.php tiki:stats %s',
                $instance->phpexec,
                '--json'
            );

            $command = $access->createCommand($script);
            $result = $access->runCommand($command);

            if ($result->getReturn() != 0) {
                $io->writeln(sprintf("<error>Couldn't get stats from instance: %s</error>", $instance->id));
                continue;
            }

            $cmdOutput = $result->getStdoutContent();
            $rows = json_decode($cmdOutput, true);

            if (empty($rows)) {
                $message = 'Unable to parse instance ' . $instance->name . ' response:' . PHP_EOL . $cmdOutput;
                $io->error($message);
                continue;
            }

            if (!$fileOpt && !$pushToOpt) {
                $io->section(sprintf('Instance %s stats:', $instance->name));
                $table = new Table($output);
                $headers = array_map(function ($headerValue) {
                    return ucwords($headerValue);
                }, array_keys($rows[0]));
                $table
                    ->setHeaders($headers)
                    ->setRows($rows);
                $table->render();
                continue;
            }

            $header = array_merge($header, array_keys($rows[0]));
            foreach ($rows as $row) {
                $exportRow = [
                    md5($instance->id . '-' . $row['kpi']),
                    $instance->id,
                    $instance->name
                ];
                $exportRow = array_merge($exportRow, $row);

                $data[] = $exportRow;
            }
        }

        if (empty($data)) {
            return 0;
        }

        chdir($currentCwd);
        array_unshift($data, $header);

        $fp = fopen($tmpFile, 'w');
        foreach ($data as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);

        $hasErrors = false;

        if (!file_exists($tmpFile)) {
            $io->writeln(sprintf('<error>File %s could not be created.</error>', $fileOpt));
            $hasErrors = true;
        }

        if ($pushToOpt) {
            $targetFile = preg_replace('/^TIKI_ROOT/', $toInstance->webroot, $fileOpt);

            $toInstance->getBestAccess('scripting')->uploadFile($tmpFile, $targetFile);
            if (!$toInstance->getBestAccess('scripting')->fileExists($targetFile)) {
                $io->writeln(sprintf(
                    '<error>Failed to upload stats file %s into instance %</error>',
                    $targetFile,
                    $toInstance->id
                ));
                $hasErrors = true;
            } else {
                $io->writeln(sprintf(
                    '<info>Stats file %s uploaded to instance %s</info>',
                    $targetFile,
                    $toInstance->id
                ));
            }
        }

        // Output file locally
        if ($fileOpt && empty($pushToOpt)) {
            rename($tmpFile, $fileOpt);
            $io->writeln('<info>Stats exported.</info>');
        }

        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }

        return (int)($hasErrors !== false);
    }

    /**
     * Get instance to query
     * @param string $instancesOpt
     * @param string $excludesOpt
     * @return array instances
     */
    private function getInstances($instancesOpt, $excludesOpt = '')
    {
        $instances = CommandHelper::getInstances('all', true);
        if (strtolower($instancesOpt) != 'all') {
            $instances = CommandHelper::validateInstanceSelection($instancesOpt, $instances);
        }

        $toExclude = explode(',', $excludesOpt);
        foreach ($toExclude as $toDelete) {
            unset($instances[$toDelete]);
        }

        return $instances;
    }
}
