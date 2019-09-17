<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Libs\Helpers\Checksum;

class CheckInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:check')
            ->setDescription('Check instance')
            ->setHelp('This command allows you to check an instance')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_OPTIONAL,
                'List of instance IDs to be checked, separated by comma (,)'
            )
            ->addOption(
                'update-from',
                null,
                InputOption::VALUE_OPTIONAL,
                'Action related to how checksums are performed. Accepted values - current or source'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        if (isset($instancesInfo)) {
            $instancesOption = $input->getOption('instances');

            if (empty($instancesOption)) {
                $io->newLine();
                CommandHelper::renderInstancesTable($output, $instancesInfo);

                $io->newLine();
                $io->writeln('<comment>In case you want to check more than one instance, please use a comma (,) between the values</comment>');

                $question = CommandHelper::getQuestion('Which instance(s) do you want to check', null, '?');
                $question->setValidator(function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                });

                $selectedInstances = $helper->ask($input, $output, $question);
            } else {
                CommandHelper::validateInstanceSelection($instancesOption, $instances);
                $instancesOption = explode(',', $instancesOption);
                $selectedInstances = array_intersect_key($instances, array_flip($instancesOption));
            }

            foreach ($selectedInstances as $instance) {
                $version = $instance->getLatestVersion();

                if (! $version) {
                    $io->writeln('<comment>Instance [' . $instance->id . '] (' . $instance->name . ') does not have a registered version. Skip.</comment>');
                    continue;
                }

                $io->writeln('<fg=cyan>Checking instance: ' . $instance->name . '...</>');

                $versionRevision = $version->revision;
                $tikiRevision = $instance->getRevision();

                if (!empty($versionRevision) && $versionRevision == $tikiRevision && $version->hasChecksums()) {
                    Checksum::handleCheckResult($instance, $version, $version->performCheck($instance), $io);
                    continue;
                }

                $fetchChecksum = false;

                if (empty($versionRevision)) {
                    warning('No revision detected for instance.');
                    $fetchChecksum = true;
                }

                if (!empty($versionRevision) && $versionRevision != $tikiRevision) {
                    warning('Revision mismatch between Tiki Manager version and instance.');
                    $fetchChecksum = true;
                }

                if (empty($trimInstanceRevision) || $trimInstanceRevision != $tikiRevision) {
                    warning('It is recommended to fetch new checksum information.');
                    $fetchChecksum = true;
                }

                if (! $version->hasChecksums()) {
                    warning('No checksums exist.');
                    $fetchChecksum = true;
                }

                if ($fetchChecksum) {
                    // Create a new version
                    $version = $instance->createVersion();
                    /** @var Application_Tiki $app */
                    $app = $instance->getApplication();
                    $version->type = $app->getInstallType();
                    $version->branch = $app->getBranch();
                    $version->date = date('Y-m-d');
                    $version->save();

                    $updateFromOption = $input->getOption('update-from');
                    if (empty($updateFromOption)) {
                        $io->writeln('<comment>No checksums exist.</comment>');
                        $io->newLine();
                        CommandHelper::renderCheckOptionsAndActions($output);
                        $io->newLine();

                        $question = new ChoiceQuestion(
                            'Please select an option to apply:',
                            ['current', 'source', 'skip'],
                            null
                        );
                        $question->setErrorMessage('Option %s is invalid.');
                        $option = $helper->ask($input, $output, $question);
                    } else {
                        if (! in_array($updateFromOption, ['current', 'source'])) {
                            throw new InvalidArgumentException("Invalid value for option 'update-from'. Accepted values: current or source.");
                        }

                        $option = $updateFromOption;
                    }

                    if ($option == 'skip') {
                        continue;
                    }

                    switch ($option) {
                        case 'source':
                            $version->collectChecksumFromSource($instance);
                            Checksum::handleCheckResult($instance, $version, $version->performCheck($instance), $io);
                            break;
                        case 'current':
                            $version->collectChecksumFromInstance($instance);
                            break;
                    }
                }
            }
        } else {
            $io->writeln('<comment>No instances available to check.</comment>');
        }
    }
}
