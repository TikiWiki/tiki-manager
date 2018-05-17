<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class SSH_Host
{
    private static $resources = array();

    private $location;
    private $env = array();

    private $host;
    private $user;
    private $port;

    private $copy_id_port_in_host;

    function __construct($host, $user, $port)
    {
        $this->host = $host;
        $this->user = $user;
        $this->port = $port;

        $this->copy_id_port_in_host = true;

        $ph = popen('ssh-copy-id -h 2>&1', 'r');
        if (! is_resource($ph))
            error('Required command (ssh-copy_id) not found.');
        else {
            if (preg_match('/p port/', stream_get_contents($ph)))
                $this->copy_id_port_in_host = false;
            pclose($ph);
        }
    }

    function chdir($location)
    {
        $this->location = $location;
    }

    function setenv($var, $value)
    {
        $this->env[$var] = $value;
    }

    private function getExtHandle()
    {
        $host = $this->host;
        $user = $this->user;
        $port = $this->port;

        $key = "$user@$host:$port";

        if (isset(self::$resources[$key]))
            return self::$resources[$key];

        $handle = new \phpseclib\Net\SSH2($host, $port);


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

    function setupKey($publicKeyFile)
    {
        // Check key presence first if possible
        if ($this->getExtHandle()) {
            return;
        }
        else {
            // Pretend this check never happened, connection will be
            // succesful after key set-up
            unset(self::$resources["{$this->user}@{$this->host}:{$this->port}"]);
        }

        $file = escapeshellarg($publicKeyFile);

        if ($this->copy_id_port_in_host) {
            $host = escapeshellarg("-p {$this->port} {$this->user}@{$this->host}");
            `ssh-copy-id -i $file $host`;
        }
        else {
            $port = escapeshellarg($this->port);
            $host = escapeshellarg("{$this->user}@{$this->host}");
            `ssh-copy-id -i $file -p $port $host`;
        }
    }

    function runCommands($commands, $output = false)
    {
        if (! is_array($commands))
            $commands = func_get_args();

        if ($handle = self::getExtHandle()) {
            $content = '';

            foreach ($commands as $line) {
                if ($this->location)
                    $line = 'cd ' . escapeshellarg($this->location) . "; $line";

                foreach ($this->env as $key => $value)
                    $line .= "export $key=" . escapeshellarg($value) . "; $line";

                $result = $handle->exec($line, null);

                $content .= $result;
            }

            return trim($content);
        }
        else {
            $key = SSH_KEY;
            $config = SSH_CONFIG;

            if ($this->location)
                array_unshift($commands, 'cd ' . escapeshellarg($this->location));

            foreach ($this->env as $name => $value)
                array_unshift($commands, "export $name=$value");

            $string = implode(' && ', $commands);
            $fullcommand = escapeshellarg($string);

            $port = null;
            if ($this->port != 22) $port = " -p {$this->port} ";

            $command = "ssh -i $key $port -F $config {$this->user}@{$this->host} $fullcommand";
            $command .= ($output ? '' : ' 2>> /tmp/trim.output');

            $output = array();
            exec($command, $output);

            $output = implode("\n", $output);
            trim_output("REMOTE $output");

            return $output;
        }
    }

    function sendFile($localFile, $remoteFile)
    {
        if ($handle = self::getExtHandle()) {
            $scpHandle = new \phpseclib\Net\SCP($handle);
            if (! $scpHandle->put($remoteFile, file_get_contents($localFile), \phpseclib\Net\SCP::SOURCE_STRING)) {
                error("Could not create remote file $remoteFile on {$this->user}@{$this->host}");
            }
        }
        else {
            $localFile = escapeshellarg($localFile);
            $remoteFile = escapeshellarg($remoteFile);

            $key = SSH_KEY;
            $port = null;
            if ($this->port != 22) $port = " -P {$this->port} ";
            `scp -i $key $port $localFile {$this->user}@{$this->host}:$remoteFile`;

            $this->runCommands("chmod 0644 $remoteFile");
        }
    }

    function receiveFile($remoteFile, $localFile)
    {
        if ($handle = self::getExtHandle()) {
            $scpHandle = new \phpseclib\Net\SCP($handle);
            if (! $scpHandle->get($remoteFile, $localFile)) {
                error("Could not create remote file $remoteFile on {$this->user}@{$this->host}");
            }
        }
        else {
            $localFile = escapeshellarg( $localFile );
            $remoteFile = escapeshellarg( $remoteFile );

            $key = SSH_KEY;
            $port = null;
            if ($this->port != 22) $port = " -P {$this->port} ";
            `scp -i $key $port {$this->user}@{$this->host}:$remoteFile $localFile`;
        }
    }

    function openShell($workingDir = '')
    {
        $key = SSH_KEY;
        $port = null;
        if ($this->port != 22) $port = " -p {$this->port} ";
        if (strlen($workingDir) > 0) {
            $command = "ssh $port -i $key {$this->user}@{$this->host} " .
                "-t 'cd {$workingDir}; pwd; bash --login'";
        }
        else
            $command = "ssh $port -i $key {$this->user}@{$this->host}";
        
        passthru($command);
    }

    function rsync($args=array())
    {
        $return_val = -1;

        if(empty($args['src']) || empty($args['dest'])) {
            return $return_val;
        }

        $key = SSH_KEY;
        $user = $this->user;
        $host = $this->host;
        
        $port = null;
        if ($this->port != 22) $port = " -p {$this->port} ";

        $output = array();
        $command = 'rsync -aL --delete -e ' .
            "\"ssh $port -i $key -l $user\" $user@$host:{$args['src']} '{$args['dest']}'";
        exec($command, $output, $return_var);
        if ($return_var != 0)
            info("RSYNC exit code: $return_var");

        return $return_var;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
