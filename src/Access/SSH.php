<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Access;

use TikiManager\Libs\Host\SSH as SSHHost;
use TikiManager\Libs\Host\Command;
use TikiManager\Application\Instance;

class SSH extends Access implements ShellPrompt
{
    private $location;
    private $env = [];
    private $changeLocation = null;

    public function __construct(Instance $instance)
    {
        parent::__construct($instance, 'ssh');
        $this->port = 22;
    }

    public function getHost()
    {
        $host = new SSHHost($this->host, $this->user, $this->port);

        // change cwd before executing commands, for instance in CoreOS it may influence what
        // php interpreter version is used to execute commands, if the dir is not available
        // try the parent directory
        if ($this->changeLocation === null && !empty($this->instance->webroot)) {
            $output = $host->runCommands(['cd ' . $this->instance->webroot . ' && echo EXISTS']);
            if ($output == "EXISTS") {
                $this->changeLocation = $this->instance->webroot;
            } else {
                $output = $host->runCommands(['cd ' . dirname($this->instance->webroot) . ' && echo EXISTS']);
                if ($output == "EXISTS") {
                    $this->changeLocation = dirname($this->instance->webroot);
                }
            }
            if ($this->changeLocation === null) {
                $this->changeLocation = false;
            }
        }
        if ($this->changeLocation) {
            $host->chdir($this->changeLocation);
        }

        return $host;
    }

    public function firstConnect()
    {
        $host = $this->getHost();
        $host->setupKey($_ENV['SSH_PUBLIC_KEY']);

        info("Testing connection...");

        $host->runCommands('exit');

        $answer = promptUser(
            'After successfully entering your password, were you asked for a password again?',
            false,
            ['yes', 'no']
        );

        if ($answer == 'yes') {
            $this->changeType('ssh::nokey');
        }

        return true;
    }

    // FIXME: Expect all remote to be Unix-like machines
    public function getInterpreterPath($instance2 = null)
    {
        $host = $this->getHost();

        $sets = [
            ['which php', 'which php5', 'which php4'],
        ];
        $php_name = ['php', 'php5'];

        foreach ($sets as $attempt) {
            // Get possible paths
            $phps = $host->runCommands($attempt);
            $phps = explode("\n", $phps);

            // Check different versions
            $valid = [];
            foreach ($phps as $interpreter) {
                if (! in_array(basename($interpreter), ['php', 'php5'])) {
                    continue;
                }

                $versionInfo = $host->runCommands("$interpreter -v");
                if (preg_match('/PHP (\d+\.\d+\.\d+)/', $versionInfo, $matches)) {
                    $valid[$matches[1]] = $interpreter;
                }
            }

            // Handle easy cases
            if (count($valid) == 0) {
                continue;
            }
            if (count($valid) == 1) {
                return reset($valid);
            }

            // List available options for user
            echo "Multiple PHP interpreters available on host:\n";
            $counter = 0;
            krsort($valid);
            $versions = array_keys($valid);
            foreach ($valid as $version => $path) {
                echo "[$counter] $path ($version)\n";
                $counter++;
            }

            // Ask user
            $counter--;
            $selection = -1;
            while (! array_key_exists($selection, $versions)) {
                $selection = readline("Which version do you want to use? (0-$counter) : ");
            }

            $version = $versions[$selection];
            return $valid[$version];
        }
    }

    public function getSVNPath()
    {
        $host = $this->getHost();

        $sets = [
            ['which svn'],
        ];
        $svn_name='svn';

        foreach ($sets as $attempt) {
            // Get possible paths
            $svns = $host->runCommands($attempt);
            $svns = explode("\n", $svns);

            // Check different versions
            $valid = [];
            foreach ($svns as $interpreter) {
                if (! in_array(basename($interpreter), [$svn_name])) {
                    continue;
                }

                $versionInfo = $host->runCommands("$interpreter --version");
                if (preg_match('/svn, version (\d+\.\d+\.\d+)/', $versionInfo, $matches)) {
                    $valid[$matches[1]] = $interpreter;
                }
            }

            // Handle easy cases
            if (count($valid) == 0) {
                continue;
            }
            if (count($valid) == 1) {
                return reset($valid);
            }

            // List available options for user
            echo "Multiple SVN'es available on host :\n";
            $counter = 0;
            krsort($valid);
            $versions = array_keys($valid);
            foreach ($valid as $version => $path) {
                echo "[$counter] $path ($version)\n";
                $counter++;
            }

            // Ask user
            $counter--;
            $selection = -1;
            while (! array_key_exists($selection, $versions)) {
                $selection = readline("Which version do you want to use? (0-$counter) : ");
            }

            $version = $versions[$selection];
            return $valid[$version];
        }
    }

    public function getInterpreterVersion($interpreter)
    {
        $host = $this->getHost();
        $versionInfo = $host->runCommands("$interpreter -r 'echo PHP_VERSION_ID;'");
        return $versionInfo;
    }

    public function getDistributionName($interpreter)
    {
        $host = $this->getHost();
        $command = file_get_contents(
            sprintf('%s/../getlinuxdistro.php', dirname(__FILE__))
        );
        $linuxName = $host->runCommands("$interpreter -r '$command'");

        return $linuxName;
    }

    public function fileExists($filename)
    {
        if ($filename{0} != '/') {
            $filename = $this->instance->getWebPath($filename);
        }

        $phpexec = $this->instance->phpexec ?? $this->getInterpreterPath($this->instance);

        $command = $this->createCommand($phpexec, ['-r', 'echo (int) file_exists(\''.$filename.'\');']);
        return $command->run()->getStdoutContent() == 1;
    }

    public function fileGetContents($filename)
    {
        $host = $this->getHost();
        $filename = escapeshellarg($filename);

        return $host->runCommands("cat $filename");
    }

    public function fileModificationDate($filename)
    {
        $host = $this->getHost();
        $root = escapeshellarg($filename);
        $data = $host->runCommands("ls -l $root");

        if (preg_match('/\d{4}-\d{2}-\d{2}/', $data, $matches)) {
            return $matches[0];
        } else {
            return null;
        }
    }

    public function runPHP($localFile, $args = [])
    {
        $host = $this->getHost();

        $remoteName = md5($localFile);
        $remoteFile = $this->instance->getWorkPath($remoteName);
        $host->runCommands(
            'mkdir -p ' . (escapeshellarg($this->instance->tempdir) ?: $_ENV['TRIM_TEMP'])
        );

        $host->sendFile($localFile, $remoteFile);
        $arg = implode(' ', array_map('escapeshellarg', $args));
        $output = $host->runCommands(
            "{$this->instance->phpexec} -q -d memory_limit=256M {$remoteFile} {$arg}",
            "rm {$remoteFile}"
        );

        return $output;
    }

    public function downloadFile($filename, $dest = '')
    {
        if ($filename{0} != '/') {
            $filename = $this->instance->getWebPath($filename);
        }

        $dot = strrpos($filename, '.');
        $ext = substr($filename, $dot);

        $local = empty($dest) ? tempnam($_ENV['TEMP_FOLDER'], 'trim') : $dest;

        $host = $this->getHost();
        $host->receiveFile($filename, $local);

        if (empty($dest)) {
            rename($local, $local . $ext);
            chmod($local . $ext, 0644);
        }

        return $local . $ext;
    }

    public function uploadFile($filename, $remoteLocation)
    {
        $host = $this->getHost();
        if ($remoteLocation{0} == '/' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $host->sendFile($filename, $remoteLocation);
        } else {
            $host->sendFile($filename, $this->instance->getWebPath($remoteLocation));
        }
    }

    public function deleteFile($filename)
    {
        if ($filename{0} != '/' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $filename = $this->instance->getWebPath($filename);
        }

        $path = escapeshellarg($filename);

        $host = $this->getHost();
        $host->runCommands("rm $path");
    }

    public function moveFile($remoteSource, $remoteTarget)
    {
        if ($remoteSource{0} != '/' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $remoteSource = $this->instance->getWebPath($remoteSource);
        }
        if ($remoteTarget{0} != '/' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $remoteTarget = $this->instance->getWebPath($remoteTarget);
        }

        $a = escapeshellarg($remoteSource);
        $b = escapeshellarg($remoteTarget);

        $this->shellExec("mv $a $b");
    }

    public function copyFile($remoteSource, $remoteTarget)
    {
        if ($remoteSource{0} != '/' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $remoteSource = $this->instance->getWebPath($remoteSource);
        }
        if ($remoteTarget{0} != '/' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $remoteTarget = $this->instance->getWebPath($remoteTarget);
        }

        $a = escapeshellarg($remoteSource);
        $b = escapeshellarg($remoteTarget);

        $this->shellExec("cp $a $b");
    }

    public function chdir($location)
    {
        $this->location = $location;
    }

    public function setenv($var, $value)
    {
        $this->env[$var] = $value;
    }

    public function shellExec($commands, $output = false)
    {
        if (!is_array($commands)) {
            $argv = func_get_args();
            $argc = count($argv);
            $commands = $argv;
            $commands = array_filter($commands, 'is_string');
            $commands = array_filter($commands, 'strlen');
            $output = is_bool($argv[$argc - 1]) && $argv[$argc - 1];
        }

        $host = $this->getHost();
        if ($this->location) {
            $host->chdir($this->location);
        }

        foreach ($this->env as $key => $value) {
            $host->setenv($key, $value);
        }

        return $host->runCommands($commands, $output);
    }

    public function createCommand($bin, $args = [], $stdin = '')
    {
        $options = [];

        if ($this->location) {
            $options['cwd'] = $this->location;
        }
        if ($this->env) {
            $options['env'] = $this->env;
        }

        $command = new Command($bin, $args, $stdin);
        $command->setOptions($options);
        $command->setHost($this->getHost());
        return $command;
    }

    public function runCommand($command, $options = [])
    {
        $host = $this->getHost();

        if ($this->location) {
            $options['cwd'] = $this->location;
        }
        if ($this->env) {
            $options['env'] = $this->env;
        }

        return $command->run($host);
    }

    public function openShell($workingDir = '')
    {
        $host = $this->getHost();
        $host->openShell($workingDir);
    }

    public function hasExecutable($command)
    {
        $command = escapeshellcmd($command);
        $exists = $this->shellExec("which $command");

        return ! empty($exists);
    }

    public function localizeFolder($remoteLocation, $localMirror)
    {
        $host = $this->getHost();
        return $host->rsync([
            'src' => $remoteLocation,
            'dest' => $localMirror
        ]);
    }
}
