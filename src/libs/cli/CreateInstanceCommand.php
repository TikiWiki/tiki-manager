<?php

namespace trim\cli\command;

use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

class CreateInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:create')
            ->setDescription('Creates a new Instance.')
            ->addArgument('name', InputArgument::REQUIRED, 'Instance name')
            ->addArgument('contact', InputArgument::REQUIRED, 'Responsible e-mail')
            ->addArgument('webroot', InputArgument::REQUIRED, 'Absolute path to instance folder')
            ->addArgument('weburl', InputArgument::REQUIRED, 'Instance URL')
            ->addArgument('type', InputArgument::REQUIRED, 'Connection type, one of ['.join(', ', \Instance::TYPES).']')
            ;

        $this
            ->addOption('blank', null, InputOption::VALUE_NONE, 'Blank instance')
            ->addOption('phpexec', null, InputOption::VALUE_REQUIRED, 'PHP binary path', PHP_BINARY)
            ->addOption('tempdir', null, InputOption::VALUE_REQUIRED, 'Temporary folder', TRIM_TEMP)
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'The host address', null)
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'The user, used to connect on remote host', null)
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'The port, used to connect on remote host', null)
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'A password, used to connect on remote host', null)
            ->addOption('backup_user', null, InputOption::VALUE_REQUIRED, 'The owner for backup files', null)
            ->addOption('backup_group', null, InputOption::VALUE_REQUIRED, 'The group for backup files', null)
            ->addOption('backup_perm', null, InputOption::VALUE_REQUIRED, 'The octal permission for backup files', '0750')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $blank = $input->getOption('blank');

        $instance = new \Instance();
        $instance->name = $input->getArgument('name');
        $instance->contact = $input->getArgument('contact');
        $instance->webroot = $input->getArgument('webroot');
        $instance->weburl = $input->getArgument('weburl');
        $instance->type = $input->getArgument('type');

        $instance->phpexec = $input->getOption('phpexec');
        $instance->tempdir = $input->getOption('tempdir');
        $instance->backup_user = $input->getOption('backup_user');
        $instance->backup_group = $input->getOption('backup_group');
        $instance->backup_perm = $input->getOption('backup_perm');
        $instance->save();

        $access = $instance->registerAccessMethod(
            $instance->type,
            $input->getOption('host'),
            $input->getOption('user'),
            $input->getOption('password'),
            $input->getOption('port')
        );
    }
}
