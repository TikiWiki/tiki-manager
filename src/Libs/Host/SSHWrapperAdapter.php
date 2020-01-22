<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Libs\Host;

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

    public function receiveFile($remoteFile, $localFile)
    {
        $localFile = escapeshellarg($localFile);
        $remoteFile = escapeshellarg($remoteFile);
        $key = $_ENV['SSH_KEY'];
        $port = null;
        if ($this->port != 22) {
            $port = " -P {$this->port} ";
        }
        return `scp -i $key $port {$this->user}@{$this->host}:$remoteFile $localFile`;
    }

    public function runCommand($command, $options = [])
    {
        $cwd = !empty($options['cwd']) ? $options['cwd'] : $this->location;
        $env = !empty($options['env']) ? $options['env'] : $this->env;

        $pipes = [];
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
            3 => ["pipe", "w"]
        ];

        $cwd = !empty($cwd) ? sprintf('cd %s;', $cwd) : '';
        $env = $this->prepareEnv($env);

        $commandLine = $this->getCommandPrefix();
        $commandLine .= ' ';
        $commandLine .= escapeshellarg($env . $cwd . $command->getFullCommand() . '; echo $? >&3');

        $process = proc_open($commandLine, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            $command->setReturn(-1);
            return $command;
        }

        $stdin = $command->getStdin();
        if (is_resource($stdin)) {
            stream_copy_to_stream($stdin, $pipes[0]);
        }
        fclose($pipes[0]);

        $stdOut = stream_get_contents($pipes[1]);
        $stdErr = stream_get_contents($pipes[2]);
        $return = stream_get_contents($pipes[3]);
        $return = intval(trim($return));
        fclose($pipes[3]);

        $command->setStdout($stdOut);
        $command->setStderr($stdErr);
        $command->setProcess($process);
        $command->setReturn($return);

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
        $fullcommand = escapeshellarg($string);

        $port = null;
        if ($this->port != 22) {
            $port = " -p {$this->port} ";
        }

        $command = "ssh -i $key $port -F $config {$this->user}@{$this->host} $fullcommand";
        $command .= ($output ? '' : ' 2>> /tmp/trim.output');

        $output = [];
        exec($command, $output);

        $output = implode("\n", $output);
        return $output;
    }

    public function sendFile($localFile, $remoteFile)
    {
        $localFile = escapeshellarg($localFile);
        $remoteFile = escapeshellarg($remoteFile);

        $key = $_ENV['SSH_KEY'];
        $port = null;
        if ($this->port != 22) {
            $port = " -P {$this->port} ";
        }
        `scp -i $key $port $localFile {$this->user}@{$this->host}:$remoteFile`;

        $this->runCommands(["chmod 0644 $remoteFile"]);
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
