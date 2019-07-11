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
            ->addArgument(
                'instances',
                InputArgument::REQUIRED,
                'Instances to fetch KPI, separated by comma (,)'
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
        $instancesArgs = $input->getArgument('instances');
        $excludesOpt = $input->getOption('exclude');
        $fileOpt = $input->getOption('file');
        $pushToOpt = $input->getOption('push-to');
        $header = ['instance_id', 'instance_name', 'name', 'value'];
        $data = [];
        $tmpFile = TEMP_FOLDER . DIRECTORY_SEPARATOR . 'kpi.csv';

        $sourceInstances = $this->getInstances($instancesArgs, $excludesOpt);
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
            $values = json_decode($cmdOutput);

            if (empty($values)) {
                $message = 'Unable to parse instance ' . $instance->name . ' response:' . PHP_EOL . $cmdOutput;
                $io->error($message);
                continue;
            }

            if (!$fileOpt && !$pushToOpt) {
                $io->section(sprintf('Instance %s stats:', $instance->name));
                $table = new Table($output);
                $table
                    ->setHeaders(['KPI', 'Value'])
                    ->setRows($values);
                $table->render();
                continue;
            }

            //fetch data
            foreach ($values as &$fields) {
                //Set instance id and name as first columns in row
                array_unshift($fields, $instance->id, $instance->name);
                $data[] = $fields;
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
     * @param string $instancesArgs
     * @param string $excludesOpt
     * @return array instances
     */
    private function getInstances($instancesArgs, $excludesOpt = '')
    {
        $instances = CommandHelper::getInstances();
        if (strtolower($instancesArgs) != 'all') {
            $instances = CommandHelper::validateInstanceSelection($instancesArgs, $instances);
        }

        $toExclude = explode(',', $excludesOpt);
        foreach ($toExclude as $toDelete) {
            unset($instances[$toDelete]);
        }

        return $instances;
    }
}
