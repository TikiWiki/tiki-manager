<?php

class SSH_Host_Seclib_Adapter {
    private $env;
    private $handle;
    private $host;
    private $location;
    private $port;
    private $user;
    private static $resources = array();

    public function __construct($host, $user, $port)
    {
        $this->setEnv(array());
        $this->setHost($host);
        $this->setPort($port ?: 22);
        $this->setUser($user);
        $this->handle = $this->getExtHandle();

        if (!$this->handle) {
            throw new SSH_Host_Seclib_Exception(
                'Unable to create PHPSecLib instance.', 1
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

        if (isset(self::$resources[$key]))
            return self::$resources[$key];

        $handle = new \phpseclib\Net\SFTP($host, $port);


        if (!$handle
            || strpos(strtoupper(php_uname('s')), 'CYGWIN') !== false //Temp Fix: phpseclib is not working ok in cygwin
        ) {
            return self::$resources[$key] = false;
        };

        $password = new \phpseclib\Crypt\RSA();
        $password->setPrivateKey(file_get_contents(SSH_KEY));
        $password->setPublicKey(file_get_contents(SSH_PUBLIC_KEY));

        if (!$handle->login($user, $password)) {
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

    private function prepareEnv($env=null) {
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

    public function receiveFile($remoteFile, $localFile)
    {
        $handle = self::getExtHandle();
        $success = $handle->get($remoteFile, $localFile);
        if (! $success) {
            error("Could not create remote file $remoteFile on {$this->user}@{$this->host}");
            return false;
        }

        $remoteSize = $handle->size($remoteFile);
        $localSize = filesize($localFile);

        if ($localSize > $remoteSize) {
            $f = fopen($localFile, 'r+');
            ftruncate($f, $remoteSize);
            fclose($f);
        }

        $remoteCheck = escapeshellarg("echo md5_file('$remoteFile');");
        $remoteCheck = $handle->exec("php -r $remoteCheck");
        $localCheck = md5_file($localFile);

        $success = $localCheck === $remoteCheck;
        return $success;
    }

    public function runCommand($command, $options=array())
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
            $stdinLen = strlen($stdin);
            $commandLine = "(head -c $stdinLen <<EOF\n{$stdin}\nEOF\n)"
                        . '| (' . $commandLine . ')';
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

    public function runCommands($commands, $output=false)
    {
        $content = '';
        foreach ($commands as $line) {
            if ($this->location)
                $line = 'cd ' . escapeshellarg($this->location) . "; $line";

            foreach ($this->env as $key => $value) {
                $line = "export $key=" . escapeshellarg($value) . "; $line";
            }

            $result = $this->handle->exec($line, null);
            $content .= $result;
        }
        return trim($content);
    }

    public function sendFile($localFile, $remoteFile)
    {
        $handle = self::getExtHandle();
        $success = $handle->put(
            $remoteFile,
            file_get_contents($localFile),
            \phpseclib\Net\SFTP::SOURCE_STRING
        );
        if (! $success) {
            error("Could not create remote file $remoteFile on {$this->user}@{$this->host}");
        }
        return $success;
    }

    public function setEnv($env)
    {
        $this->env = $env ?: array();
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

class SSH_Host_Seclib_Exception extends Exception 
{
}
