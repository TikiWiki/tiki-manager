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

    public function __construct($host, $user, $port)
    {
        $this->host = $host;
        $this->user = $user;
        $this->port = $port ?: 22;
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

        $commandLine = $this->getCommandPrefix();
        $commandLine .= ' ';
        $commandLine .= escapeshellarg('set -e; ' .$env . $cwd . $command->getFullCommand());

        $process = Process::fromShellCommandline($commandLine)
            ->setTimeout(3600);

        $stdin = $command->getStdin();
        if ($stdin) {
            $process->setInput($stdin);
        }
        $process->run();

        $command->setStdout($process->getOutput());
        $command->setStderr($process->getErrorOutput());
        $command->setProcess($process);
        $command->setReturn($process->getExitCode());

        return $command;
    }

    public function runCommands($commands, $output = false)
    {
        $key = $_ENV['SSH_KEY'];
        $config = $_ENV['SSH_CONFIG'];

        if ($this->location) {
            array_unshift($commands, 'cd ' . escapeshellarg($this->location));
        }

        foreach ($this->env as $name => $value) {
            array_unshift($commands, "export $name=$value");
        }

        $string = implode(' && ', $commands);
        $fullCommand = escapeshellarg($string);

        $port = $this->port != 22 ? " -p {$this->port} " : null;

        $command = "ssh -i $key $port -F $config {$this->user}@{$this->host} $fullCommand";
        $command .= ($output ? '' : ' 2>> '.$_ENV['TRIM_OUTPUT']);

        $process = Process::fromShellCommandline($command)
            ->setTimeout(3600);
        $process->run();

        return $process->getOutput();
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
