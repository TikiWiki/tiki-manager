<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Libs\Host;

use Symfony\Component\Process\Process;

class SSHWrapperAdapter
{
    private $host;
    private $user;
    private $port;
    private $env;
    private $location;
    private $runAsUser;

    public function __construct($host, $user, $port, $runAsUser = null)
    {
        $this->host = $host;
        $this->user = $user;
        $this->port = $port ?: 22;
        $this->runAsUser = $runAsUser;
        $_ENV['HTTP_ACCEPT_ENCODING'] = '';
        $this->env  = $_ENV ?: [];
        $this->location = '';
    }

    private function getCommandPrefix($options = [])
    {
        $options['-i'] = !empty($options['pubkey']) ? $options['pubkey'] : $_ENV['SSH_KEY'];
        $options['-F'] = !empty($options['config']) ? $options['config'] : $_ENV['SSH_CONFIG'];
        $options['-p'] = !empty($options['port']) ? $options['port'] : $this->port;

        $options = array_filter($options, 'strlen');
        $options = array_map('escapeshellarg', $options);
        $target = $this->user . '@' . $this->host;

        $prefix = 'ssh';
        foreach ($options as $key => $value) {
            $prefix .= ' ' . $key . ' ' . $value;
        }
        $prefix .= ' ' . escapeshellarg($target);

        return $prefix;
    }

    private function getHost()
    {
        return $this->host;
    }

    private function getUser()
    {
        return $this->user;
    }

    private function getPort()
    {
        return $this->port;
    }

    private function getEnv()
    {
        return $this->env;
    }

    private function getLocation()
    {
        return $this->location;
    }

    private function prepareEnv($env = null)
    {
        $line = '';
        $env = $env ?: $this->env;
        if (!is_array($env) || empty($env)) {
            return $line;
        }
        foreach ($env as $key => $value) {
            $value = preg_replace('/(\s)/', '\\\$1', $value);
            $value = sprintf('export %s=%s;', $key, escapeshellarg($value));
            $line .= $value;
        }
        return $line;
    }

    public function runCommand($command, $options = [])
    {
        $cwd = !empty($options['cwd']) ? $options['cwd'] : $this->location;
        $env = !empty($options['env']) ? $options['env'] : $this->env;

        $cwd = !empty($cwd) ? sprintf('cd %s;', $cwd) : '';
        $env = $this->prepareEnv($env);

        $command->setAccessType('ssh');

        if (!empty($this->runAsUser)) {
            $command->wrapWithSudo($this->runAsUser, $cwd);
        }

        $commandLine = $this->getCommandPrefix();
        $commandLine .= ' ';
        $commandLine .= escapeshellarg('set -e; ' .$env . $cwd . $command->getFullCommand());

        $process = Process::fromShellCommandline($commandLine)
            ->setTimeout($_ENV['COMMAND_EXECUTION_TIMEOUT']);

        $stdin = $command->getStdin();
        if ($stdin) {
            $process->setInput($stdin);
        }
        $process->run();

        $output = $process->getOutput();
        $error = $process->getErrorOutput();
        $exitCode = $process->getExitCode();

        $command->setStdout($output);
        $command->setStderr($error);
        $command->setProcess($process);
        $command->setReturn($exitCode);

        $out = (empty($output) ? '' : "\nOutput: $output") . (empty($error) ? '' : "\nError: $error");
        trim_output('SSH [' . date('Y-m-d H:i:s') . '] ' . $commandLine . ' - return: ' . $exitCode . $out);

        return $command;
    }

    public function runCommands($commands, $output = false)
    {
        if (!is_array($commands)) {
            $commands = func_get_args();
            $commands = array_filter($commands, 'is_string');
        }

        $cwd = !empty($this->location) ? sprintf('cd %s;', $this->location) : '';
        $env = $this->prepareEnv($this->env);

        $fullShellCommand = implode(' && ', $commands);

        $command = new Command($fullShellCommand);
        $command->setAccessType('ssh');

        if (!empty($this->runAsUser)) {
            $command->wrapWithSudo($this->runAsUser, $cwd);
        }

        $commandLine = $this->getCommandPrefix();
        $commandLine .= ' ';
        $commandLine .= escapeshellarg('set -e; ' . $env . $cwd . $command->getFullCommand());
        $commandLine .= ($output ? '' : ' 2>> '.$_ENV['TRIM_OUTPUT']);
        $process = Process::fromShellCommandline($commandLine)
            ->setTimeout(3600);
        $process->run();

        $output = $process->getOutput();
        $error = $process->getErrorOutput();
        $exitCode = $process->getExitCode();

        $out = (empty($output) ? '' : "\nOutput: $output") . (empty($error) ? '' : "\nError: $error");
        trim_output('SSH [' . date('Y-m-d H:i:s') . '] ' . $commandLine . ' - return: ' . $exitCode . $out);

        return $output;
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function setPort($port)
    {
        $this->port = $port ?: 22;
    }

    public function setEnv($env)
    {
        $this->env = $env ?: [];
    }

    public function setLocation($location)
    {
        $this->location = $location;
    }

    public function unsetHandle()
    {
        return true;
    }
}
