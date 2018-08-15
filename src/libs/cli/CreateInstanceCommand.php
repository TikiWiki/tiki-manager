<?php

namespace trim\cli\command;

use trim\instance\Discovery;
use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Exception\InvalidArgumentException;
use \Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Input\InputOption;
use \Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Question\Question;

class CreateInstanceCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('instance:create')
            ->setDescription('Creates a new Instance.')
            ->addArgument('name', InputArgument::REQUIRED, 'Instance name')
            ->addArgument('webroot', InputArgument::REQUIRED, 'Absolute path to instance folder')
            ->addArgument('weburl', InputArgument::REQUIRED, 'Instance URL')
            ->addArgument('type', InputArgument::REQUIRED, 'Connection type, one of ['.join(', ', explode(',', \Instance::TYPES)).']')
            ;

        $this
            ->addOption('backup_group', null, InputOption::VALUE_REQUIRED, 'The group for backup files', null)
            ->addOption('backup_perm', null, InputOption::VALUE_REQUIRED, 'The octal permission for backup files', null)
            ->addOption('backup_user', null, InputOption::VALUE_REQUIRED, 'The owner for backup files', null)
            ->addOption('blank', null, InputOption::VALUE_NONE, 'Blank instance')
            ->addOption('contact', null, InputOption::VALUE_REQUIRED, 'Responsible e-mail', null)
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'The host address', null)
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'A password, used to connect on remote host', null)
            ->addOption('phpexec', null, InputOption::VALUE_REQUIRED, 'PHP binary path', null)
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'The port, used to connect on remote host', null)
            ->addOption('tempdir', null, InputOption::VALUE_REQUIRED, 'Temporary folder', TRIM_TEMP)
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'The user, used to connect on remote host', null)
            ;
    }

    protected function check(InputInterface $input, OutputInterface $output)
    {
        $self = $this;
        $definition = $this->getDefinition();
        $fields = array_merge(
            array_keys($definition->getArguments()),
            array_keys($definition->getOptions())
        );
        $errors = array();
        foreach ($fields as $field) {
            $method = 'check' . ucfirst($field);
            $valid = (
                ! method_exists($this, $method)
                || $this->{$method}($input, $output)
            );
        }
        return true;
    }

    protected function checkType($input, $output)
    {
        $type = $input->getArgument('type');
        $required = array(
            'local' => array(),
            'ftp' => array('user', 'host', 'password'),
            'ssh' => array('user', 'host')
        );

        if(isset($required[$type])) {
            $options = array_map(array($input, 'getOption'), $required[$type]);
            $options = array_combine($required[$type], $options);
        } else {
            throw new InvalidArgumentException("Type '$type' is unknown.");
        }

        $callback = function($v, $o) { return $v && strlen($o) > 0; };
        $valid = array_reduce($options, $callback, true);

        if (!$valid) {
            $options = array_map('escapeshellarg', $required[$type]);
            throw new InvalidArgumentException(
                "For type '$type', the options [" . join(', ', $options)
                ."] are required"
            );
        }

        return $valid;
    }

    protected function checkHost($input, $output)
    {
        $type = $input->getArgument('type');
        if($type === 'local') {
            return true;
        }
        $host = $input->getOption('host');
        $port = $input->getOption('port') ?: ($type === 'ssh' ? 22 : 21);
        $sock = fsockopen($host, $port);
        $valid = is_resource($sock);
        fclose($sock);
        return $valid;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->check($input, $output);
        $blank = $input->getOption('blank');

        $instance = new \Instance();
        $instance->name = $input->getArgument('name');
        $instance->webroot = $input->getArgument('webroot');
        $instance->weburl = $input->getArgument('weburl');
        $instance->type = $input->getArgument('type');

        $instance->contact = $input->getOption('contact');
        $instance->tempdir = $input->getOption('tempdir');
        $instance->save();

        $access = $instance->registerAccessMethod(
            $instance->type,
            $input->getOption('host'),
            $input->getOption('user'),
            $input->getOption('password'),
            $input->getOption('port')
        );

        $discovery = new Discovery($instance, $access);

        $instance->phpexec = $input->getOption('phpexec');
        if(!$instance->phpexec) {
            $instance->phpexec = $discovery->detectPHP();
            $output->writeln("Detected PHP binary: {$instance->phpexec}");

            $instance->phpversion = $discovery->detectPHPVersion();
            $output->writeln("Detected PHP version: {$instance->phpversion}");
        }

        $backup_perm = $discovery->detectBackupPerm();
        $instance->backup_user = $input->getOption('backup_user') ?: $backup_perm[0];
        $instance->backup_group = $input->getOption('backup_group') ?: $backup_perm[1];
        $instance->backup_perm = intval($input->getOption('backup_perm'), 8) ?: $backup_perm[2];
        $instance->save();

        if($blank) {
            exit(0);
        }

        perform_instance_installation($instance);
    }
}
