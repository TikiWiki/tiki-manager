<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Access\Access;
use TikiManager\Config\App;

class EditInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('instance:edit')
            ->setDescription('Edit instance')
            ->setHelp('This command allows you to modify an instance which is managed by Tiki Manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        if (isset($instancesInfo)) {
            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);

            $this->io->newLine();
            $output->writeln('<comment>In case you want to edit more than one instance, please use a comma (,) between the values</comment>');

            $helper = $this->getHelper('question');
            $question = CommandHelper::getQuestion('Which instance(s) do you want to edit', null, '?');
            $question->setValidator(function ($answer) use ($instances) {
                return CommandHelper::validateInstanceSelection($answer, $instances);
            });

            $selectedInstances = $helper->ask($input, $output, $question);
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Edit data for ' . $instance->name . '...</>');

                $result = query(Access::SQL_SELECT_ACCESS, [':id' => $instance->id]);
                $instanceType = $result->fetch()['type'];
                unset($result);

                if ($instanceType != 'local') {
                    $question = CommandHelper::getQuestion('Host name', $instance->name);
                } elseif ($instanceType == 'local') {
                    $question = CommandHelper::getQuestion('Instance name', $instance->name);
                }
                $name = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('Contact email', $instance->contact);
                $contact = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('Web root', $instance->webroot);
                $webroot = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('Web URL', $instance->weburl);
                $weburl = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('Working directory', $instance->tempdir);
                $tempdir = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('Backup owner', $instance->getProp('backup_user'));
                $backup_user = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('Backup group', $instance->getProp('backup_group'));
                $backup_group = $helper->ask($input, $output, $question);

                $backup_perm = intval($instance->getProp('backup_perm') ?: 0775);
                $question = CommandHelper::getQuestion('Backup file permissions', decoct($backup_perm));
                $backup_perm = $helper->ask($input, $output, $question);

                $instance->name = $name;
                $instance->contact = $contact;
                $instance->webroot = rtrim($webroot, '/');
                $instance->weburl = rtrim($weburl, '/');
                $instance->tempdir = rtrim($tempdir, '/');
                $instance->backup_user = $backup_user;
                $instance->backup_group = $backup_group;
                $instance->backup_perm = octdec($backup_perm);
                $instance->save();

                $output->writeln('<info>Instance information saved.</info>');
            }
        } else {
            $output->writeln('<comment>No Tiki instances available to edit.</comment>');
        }
    }
}
