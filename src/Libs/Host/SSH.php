<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Libs\Host;

use TikiManager\Config\App;

class SSH
{
    private static $sshKeyCheck = [];

    private $adapter;
    private $location;
    private $env = [];

    private $host;
    private $user;
    private $port;

    private $copy_id_port_in_host;

    protected $io;

    public function __construct($host, $user, $port, $adapter_class = null)
    {
        $this->host = $host ?: '';
        $this->user = $user ?: '';
        $this->port = $port ?: 22;
        $this->checkCopyId();
        $this->selectAdapter($adapter_class);
        $this->setenv('HTTP_ACCEPT_ENCODING', '');

        $this->io = App::get('io');

        $sshConnectionId = implode('_', [$this->user,$this->host, $this->port]);
        if (!isset(self::$sshKeyCheck[$sshConnectionId])) {
            self::$sshKeyCheck[$sshConnectionId] = $this->checkSshKey();
        }
    }

    public function chdir($location)
    {
        $this->location = $location;
        $this->adapter->setLocation($location);
    }

    public function checkCopyId()
    {
        $this->copy_id_port_in_host = true;
        $ph = popen('ssh-copy-id -h 2>&1', 'r');
        if (! is_resource($ph)) {
            $this->io->error('Required command (ssh-copy_id) not found.');
        } else {
            if (preg_match('/p port/', stream_get_contents($ph))) {
                $this->copy_id_port_in_host = false;
            }
            pclose($ph);
        }
    }

    public function setenv($var, $value)
    {
        $this->env[$var] = $value;
        $this->adapter->setEnv($this->env);
    }

    public function setupKey($publicKeyFile)
    {
        $this->adapter->unsetHandle();
        $file = escapeshellarg($publicKeyFile);

        if ($this->copy_id_port_in_host) {
            $host = escapeshellarg("-p {$this->port} {$this->user}@{$this->host}");
            `ssh-copy-id -i $file $host`;
        } else {
            $port = escapeshellarg($this->port);
            $host = escapeshellarg("{$this->user}@{$this->host}");
            `ssh-copy-id -i $file -p $port $host`;
        }
    }

    public function runCommand($command, $options = [])
    {
        return $this->adapter->runCommand($command, $options);
    }

    public function runCommands($commands, $output = false)
    {
        if (!is_array($commands)) {
            $commands = func_get_args();
            $output = end($commands) === true;
            $commands = array_filter($commands, 'is_string');
        }
        return $this->adapter->runCommands($commands, $output);
    }

    public function sendFile($localFile, $remoteFile)
    {
        $exitCode = $this->rsync([
            'src' => $localFile,
            'dest' => $remoteFile,
        ]);

        if ($exitCode == 0) {
            $this->runCommands(["chmod 0644 $remoteFile"]);
        }

        return $exitCode == 0;
    }

    public function receiveFile($remoteFile, $localFile)
    {
        $exitCode = $this->rsync([
            'src' => $remoteFile,
            'dest' => $localFile,
            'download' => true
        ]);

        return $exitCode == 0;
    }

    public function openShell($workingDir = '')
    {
        $key = $_ENV['SSH_KEY'];
        $port = null;
        if ($this->port != 22) {
            $port = " -p {$this->port} ";
        }
        if (strlen($workingDir) > 0) {
            $command = "ssh $port -i $key {$this->user}@{$this->host} " .
                "-t 'cd {$workingDir}; pwd; bash --login'";
        } else {
            $command = "ssh $port -i $key {$this->user}@{$this->host}";
        }

        if (! empty($_ENV['RUN_THROUGH_TIKI_WEB'])) {
            return $command;
        }

        passthru($command);
    }

    public function rsync($args = [])
    {
        $return_val = -1;
        if (empty($args['src']) || empty($args['dest'])) {
            return $return_val;
        }

        $exclude = [];
        if (!empty($args['exclude'])) {
            $exclude = is_array($args['exclude']) ? $args['exclude'] : [$args['exclude']];
            $exclude = array_map(function ($path) {
                return '--exclude=' . $path;
            }, $exclude);
        }

        $key = $_ENV['SSH_KEY'];
        $user = $this->user;
        $host = $this->host;
        $src = $args['src'];
        $dest = $args['dest'];
        $port = $this->port ;

        $localHost = new Local();

        // path may be escaped, in that case we un-escape, since we will escape after
        if (
            substr($src, 0, 1) === "'" && substr($src, -1) === "'"
            || substr($src, 0, 1) === '"' && substr($src, -1) === '"'
        ) {
            $src = substr($src, 1, -1);
        }

        // is it relative
        if ($src[0] !== '/' && ! empty($this->location)) {
            $src = $this->location . '/' . $src;
        }

        $rsyncParams = ['-a', '-L', '--delete'];
        $rsyncParams = array_merge($rsyncParams, $exclude);
        $rsyncParams[] = '-e';
        $rsyncParams[] = "ssh -p {$port} -i $key";
        if (isset($args['download']) && $args['download']) {
            $rsyncParams[] = "{$user}@{$host}:".escapeshellarg($src);
            $rsyncParams[] = $dest;
        } else {
            $rsyncParams[] = $src;
            $rsyncParams[] = "{$user}@{$host}:".escapeshellarg($dest);
        }

        $command = new Command('rsync', $rsyncParams);
        $localHost->runCommand($command);
        $return_var = $command->getReturn();

        if ($return_var != 0) {
            $this->io->error("RSYNC exit code: $return_var");
        }

        return $return_var;
    }

    private function selectAdapter($className)
    {
        // keep SSHSeclibAdapter as the default only for windows.
        $defaultClass = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'TikiManager\Libs\Host\SSHSeclibAdapter' : 'TikiManager\Libs\Host\SSHWrapperAdapter';
        $className = $className ?: $defaultClass;

        try {
            $this->adapter = new $className(
                $this->host,
                $this->user,
                $this->port
            );
        } catch (\Exception $e) {
            $this->adapter = new SSHWrapperAdapter(
                $this->host,
                $this->user,
                $this->port
            );
            debug("Unable to use $className, falling back to SSHWrapperAdapter");
        }
        return $this->adapter;
    }

    /**
     * Check if Private Key is within the authorized keys from remote server
     */
    private function checkSshKey()
    {
        $params = [
            '-i',
            $_ENV['SSH_KEY'],
            '-o',
            "IdentitiesOnly yes",
            '-o',
            "PreferredAuthentications publickey",
        ];

        if (! empty($_ENV['RUN_THROUGH_TIKI_WEB'])) {
            // Tiki web could use www-data user which fails host key checking
            $params[] = '-o';
            $params[] = "StrictHostKeyChecking no";
        }

        $params[] = "{$this->user}@{$this->host}";
        $params[] = "exit";

        if ($this->port !== '22') {
            array_unshift($params, '-p', $this->port);
        }

        $localHost = new Local();
        $command = new Command('ssh', $params);

        $localHost->runCommand($command);
        $returnVar = $command->getReturn();

        if ($returnVar != 0) {
            $message = "Your ssh keys are not properly set up. Please use 'tiki-manager instance:copysshkey' command.";
            if ($err = $command->getStderrContent()) {
                $message .= ' ' . $err;
            }
            $this->io->error($message);
            return false;
        }

        return true;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
