<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;

class RestoreInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:restore')
            ->setDescription('Restore a blank installation')
            ->setHelp('This command allows you to restore a blank installation');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $instances = CommandHelper::getInstances('no-tiki');
        $instancesInfo = CommandHelper::getInstancesInfo($instances);

        $restorableInstances = CommandHelper::getInstances('restore');
        $restorableInstancesInfo = CommandHelper::getInstancesInfo($restorableInstances);

        if (isset($instancesInfo) && isset($restorableInstancesInfo)) {
            $output->writeln('<comment>NOTE: It is only possible to restore a backup on a blank install.</comment>');
            $output->writeln('<comment>WARNING: If you are restoring to the same server, this can lead to</comment>');
            $output->writeln('<comment>data corruption as both the original and restored Tiki are using the</comment>');
            $output->writeln('<comment>same folder for storage.</comment>');

            $io->newLine();
            $renderResult = CommandHelper::renderInstancesTable($output, $instancesInfo);

            $helper = $this->getHelper('question');
            $question = CommandHelper::getQuestion('Which instance(s) do you want to restore to', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Instance to restore to: ' . $instance->name . '</>');

                $io->newLine();
                $renderResult = CommandHelper::renderInstancesTable($output, $restorableInstancesInfo);

                $question = CommandHelper::getQuestion('Which instance do you want to restore from', null, '?');
                $question->setValidator(function ($answer) use ($restorableInstances) {
                    return CommandHelper::validateInstanceSelection($answer, $restorableInstances);
                });
                $selectedRestorableInstances = $helper->ask($input, $output, $question);
                $restorableInstance = reset($selectedRestorableInstances);

                $files = $restorableInstance->getArchives();
                foreach ($files as $key => $path) {
                    $output->writeln('[' . $key .'] ' . basename($path));
                }

                $question = CommandHelper::getQuestion('Which backup do you want to restore', null, '?');
                $seletecArchive = $helper->ask($input, $output, $question);

                if (! $file = reset(getEntries($files, $seletecArchive))) {
                    $output->writeln('<comment>Skip: No archive file selected.</comment>');
                    continue;
                }

                $instance->restore($restorableInstance->app, $file);

                $output->writeln('<fg=cyan>It is now time to test your site: ' . $instance->name . '</>');
                $output->writeln('<fg=cyan>If there are issues, connect with make access to troubleshoot directly on the server.</>');
                $output->writeln('<fg=cyan>You\'ll need to login to this restored instance and update the file paths with the new values.</>');
            }
        } else {
            $output->writeln('<comment>No instances available to restore to/from.</comment>');
        }
    }
}
