<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Libs\Host;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use TikiManager\Libs\Host\Exception\SSHSeclibException;

class SSHSeclibAdapter
{
    private $env;
    private $handle;
    private $host;
    private $location;
    private $port;
    private $user;
    private static $resources = [];

    public function __construct($host, $user, $port)
    {
        $this->setenv('HTTP_ACCEPT_ENCODING', '');
        $this->setHost($host);
        $this->setPort($port ?: 22);
        $this->setUser($user);
        $this->handle = $this->getExtHandle();

        if (!$this->handle) {
            throw new SSHSeclibException(
                'Unable to create PHPSecLib instance.',
                1
            );
        }
    }

    private function getEnv()
    {
        return $this->env;
    }

    private function getExtHandle()
    {
        $host = $this->host;
        $user = $this->user;
        $port = $this->port;

        $key = "$user@$host:$port";

        if (isset(self::$resources[$key])) {
            return self::$resources[$key];
        }

        $handle = new SFTP($host, $port);


        if (!$handle
            || strpos(strtoupper(php_uname('s')), 'CYGWIN') !== false //Temp Fix: phpseclib is not working ok in cygwin
        ) {
            return self::$resources[$key] = false;
        };

        if (!$handle->login($user, PublicKeyLoader::loadPrivateKey(file_get_contents($_ENV['SSH_KEY'])))) {
            return self::$resources[$key] = false;
        };

        return self::$resources[$key] = $handle;
    }

    private function getHost()
    {
        return $this->host;
    }

    private function getLocation()
    {
        return $this->location;
    }

    private function getPort()
    {
        return $this->port;
    }

    private function getUser()
    {
        return $this->user;
    }

    private function prepareEnv($env = null)
    {
        $line = '';
        $env = $env ?: $this->env;
        if (!is_array($env) || empty($env)) {
            return $line;
        }
        foreach ($env as $key => $value) {
            $line .= $key . '=' . escapeshellarg($value) . ';';
        }
        return $line;
    }

    public function runCommand($command, $options = [])
    {
        $handle = self::getExtHandle();
        $cwd = !empty($options['cwd']) ? $options['cwd'] : $this->location;
        $env = !empty($options['env']) ? $options['env'] : $this->env;

        $quietMode = $handle->isQuietModeEnabled();
        $handle->disableQuietMode();

        $commandLine = '';
        if ($cwd) {
            $commandLine .= 'cd ' . escapeshellarg($cwd) . ';';
        }

        $commandLine .= $command->getFullCommand();
        $stdin = $command->getStdinContent();

        if ($stdin) {
            $stdin = base64_encode($stdin);
            $commandLine = "(base64 -d <<EOF\n{$stdin}\nEOF\n)"
                        . ' | (' . $commandLine . ')';
        }

        $envLine = $this->prepareEnv($env);
        $commandLine = $envLine . $commandLine;
        $stdout = $handle->exec($commandLine);
        $command->setStdout(rtrim($stdout));

        $stderr = $handle->getStdError();
        $command->setStderr($stderr);

        $return = $handle->getExitStatus();
        $command->setReturn($return);

        if ($quietMode) {
            $handle->enableQuietMode();
        }

        return $command;
    }

    public function runCommands($commands, $output = false)
    {
        $content = '';
        foreach ($commands as $line) {
            if ($this->location) {
                $line = 'cd ' . escapeshellarg($this->location) . "; $line";
            }

            foreach ($this->env as $key => $value) {
                $line = "export $key=" . escapeshellarg($value) . "; $line";
            }

            $result = $this->handle->exec($line, null);
            $content .= $result;
        }
        return trim($content);
    }

    public function setEnv($env)
    {
        $this->env = $env ?: [];
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    public function setLocation($location)
    {
        $this->location = $location;
    }

    public function setPort($port)
    {
        $this->port = $port ?: 22;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function unsetHandle()
    {
        unset(self::$resources["{$this->user}@{$this->host}:{$this->port}"]);
    }
}
