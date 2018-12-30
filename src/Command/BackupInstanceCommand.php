<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use TikiManager\Command\Helper\CommandHelper;

class BackupInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:backup')
            ->setDescription('Backup instance')
            ->setHelp('This command allows you to backup instances')
            ->addArgument('instances', InputArgument::IS_ARRAY | InputArgument::OPTIONAL)
            ->addOption('exclude', 'e', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Exclude the backup of specific instance IDs');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances('all', true);
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $helper = $this->getHelper('question');

            $arguments = $input->getArgument('instances');
            if (! empty($arguments)) {
                if ($arguments[0] == 'all') {
                    $this->checkForExcludedInstances($instances);
                    $selectedInstances = $instances;
                } else {
                    $instancesIds = array_slice($arguments, 0);

                    $selectedInstances = [];
                    foreach ($instancesIds as $index) {
                        if (array_key_exists($index, $instances)) {
                            $selectedInstances[] = $instances[$index];
                        }
                    }
                }
            } else {
                $io->newLine();
                $output->writeln('<comment>NOTE: Backups are only available on Local and SSH instances.</comment>');

                $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

                $io->newLine();
                $output->writeln('<comment>In case you want to backup more than one instance, please use a comma (,) between the values</comment>');

                $question = CommandHelper::getQuestion('Which instance(s) do you want to backup', null, '?');
                $question->setValidator(function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                });

                $selectedInstances = $helper->ask($input, $output, $question);
            }

            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Performing backup for ' . $instance->name . '</>');
                $instance->backup();
                perform_archive_cleanup($instance->id, $instance->name);
            }
        } else {
            $output->writeln('<comment>No instances available to backup.</comment>');
        }
    }

    /**
     * Function used to check for instances to exclude via option "--exclude="
     * @param $instances
     */
    private function checkForExcludedInstances(&$instances)
    {
        $excluded_option = CommandHelper::getCliOption('exclude');
        if (empty($excluded_option)) {
            return;
        }

        $instances_to_exclude = explode(',', CommandHelper::getCliOption('exclude'));
        foreach ($instances as $key => $instance) {
            if (in_array($instance->id, $instances_to_exclude)) {
                unset($instances[$key]);
            }
        }
    }
}
