<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Access\Access;
use TikiManager\Config\App;
use Symfony\Component\Console\Input\InputOption;
use TikiManager\Application\Instance;

use function GuzzleHttp\json_decode;

class EditInstanceCommand extends TikiManagerCommand
{
    protected function configure()
    {
        $this
            ->setName('instance:edit')
            ->setDescription('Edit instance')
            ->setHelp('This command allows you to modify an instance which is managed by Tiki Manager')
            ->addOption(
                'instances',
                'i',
                InputOption::VALUE_REQUIRED,
                'List of instance IDs to be edited, separated by comma (,)'
            )
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_REQUIRED,
                'Instance web url'
            )
            ->addOption(
                'name',
                'na',
                InputOption::VALUE_REQUIRED,
                'Instance name'
            )
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Instance contact email'
            )
            ->addOption(
                'webroot',
                'wr',
                InputOption::VALUE_REQUIRED,
                'Instance web root'
            )
            ->addOption(
                'tempdir',
                'td',
                InputOption::VALUE_REQUIRED,
                'Instance temporary directory'
            )
            ->addOption(
                'backup-user',
                'bu',
                InputOption::VALUE_REQUIRED,
                'Instance backup user'
            )
            ->addOption(
                'backup-group',
                'bg',
                InputOption::VALUE_REQUIRED,
                'Instance backup group'
            )
            ->addOption(
                'backup-permission',
                'bp',
                InputOption::VALUE_REQUIRED,
                'Instance backup permission'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instances = CommandHelper::getInstances();
        $instancesInfo = CommandHelper::getInstancesInfo($instances);
        
        if (isset($instancesInfo)) {
            $this->io->newLine();
            $output->writeln('<comment>Instances list</comment>');
            $this->io->newLine();
            CommandHelper::renderInstancesTable($output, $instancesInfo);
            $this->io->newLine();
            $output->writeln('<comment>In case you want to edit more than one instance, please use a comma (,) between the values</comment>');
            $helper = $this->getHelper('question');

            if (empty($input->getOption('instances'))) {
                $question = CommandHelper::getQuestion('Which instance(s) do you want to edit', null, '?');
                $question->setValidator(function ($answer) use ($instances) {
                    return CommandHelper::validateInstanceSelection($answer, $instances);
                });
                $selectedInstances = $helper->ask($input, $output, $question);
            }else{
                $selectedInstances = CommandHelper::validateInstanceSelection($input->getOption('instances'), $instances);
            }
            
            foreach ($selectedInstances as $instance) {
                $output->writeln('<fg=cyan>Edit data for ' . $instance->name . '</>');

                //Instance name
                if (empty($input->getOption('name'))) {
                    if ($instance->type != 'local') {
                        $question = CommandHelper::getQuestion('Host name', $instance->name);
                    } elseif ($instance->type == 'local') {
                        $question = CommandHelper::getQuestion('Instance name', $instance->name);
                    }
                    $name = $helper->ask($input, $output, $question);
                }else{
                    $name = $input->getOption('name');
                }

                //Instance email
                if (empty($input->getOption('email'))) {
                    $question = CommandHelper::getQuestion('Contact email', $instance->contact);
                    $contact = $helper->ask($input, $output, $question);
                }else{
                    $contact = $input->getOption('email');
                }

                //Instance webroot
                if (empty($input->getOption('webroot'))) {
                    $question = CommandHelper::getQuestion('Web root', $instance->webroot);
                    $webroot = $helper->ask($input, $output, $question);
                }else{
                    $webroot = $input->getOption('webroot');
                }

                //Instance Web URL
                if (empty($input->getOption('url'))) {
                    $question = CommandHelper::getQuestion('Web URL', $instance->weburl);
                    $weburl = $helper->ask($input, $output, $question);
                }else{
                    $weburl = $input->getOption('url');
                }

                //Instance Working directory
                if (empty($input->getOption('tempdir'))) {
                    $question = CommandHelper::getQuestion('Working directory', $instance->tempdir);
                    $tempdir = $helper->ask($input, $output, $question);
                }else{
                    $tempdir = $input->getOption('tempdir');
                }

                //Instance Backup user
                if (empty($input->getOption('backup-user'))) {
                    $question = CommandHelper::getQuestion('Backup owner', $instance->getProp('backup_user'));
                    $backup_user = $helper->ask($input, $output, $question);
                }else{
                    $backup_user = $input->getOption('backup-user');
                }

                //Instance Backup group
                if (empty($input->getOption('backup-group'))) {
                    $question = CommandHelper::getQuestion('Backup group', $instance->getProp('backup_group'));
                    $backup_group = $helper->ask($input, $output, $question);
                }else{
                    $backup_group = $input->getOption('backup-group');
                }

                //Instance Backup permission
                if (empty($input->getOption('backup-permission'))) {
                    $backup_perm = intval($instance->getProp('backup_perm') ?: 0775);
                    $question = CommandHelper::getQuestion('Backup file permissions', decoct($backup_perm));
                    $backup_perm = $helper->ask($input, $output, $question);
                }else{
                    $backup_perm = $input->getOption('backup-permission');
                }

                $instance->name = $name;
                $instance->contact = $contact;
                $instance->webroot = rtrim($webroot, '/');
                $instance->weburl = rtrim($weburl, '/');
                $instance->tempdir = rtrim($tempdir, '/');
                $instance->backup_user = $backup_user;
                $instance->backup_group = $backup_group;
                $instance->backup_perm = octdec($backup_perm);
                $instance->save();
                
                $this->io->newLine();
                $output->writeln('<comment>'. $instance->name .' instance information has been modified successfully.</comment>');
                $this->io->newLine();
            }
        } else {
            $output->writeln('<comment>No Tiki instances available to edit.</comment>');
        }
    }
}
