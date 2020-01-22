<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Access;

use TikiManager\Libs\Host\Local as LocalHost;
use TikiManager\Libs\Host\Command;
use TikiManager\Application\Instance;
use TikiManager\Libs\Helpers\ApplicationHelper;

class Local extends Access implements ShellPrompt
{
    private $location;
    private $env = [];
    private $hostlib = null;
    private $changeLocation = null;

    public function __construct(Instance $instance)
    {
        parent::__construct($instance, 'local');
        $this->setenv('HTTP_ACCEPT_ENCODING', '');
    }

    private function isLocalPath($pathname)
    {
        if ($pathname{0} == '/') {
            return true;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && preg_match("|^[a-zA-Z]:[/\\\\]|", $pathname)) {
            return true;
        }

        return false;
    }

    public function getHost()
    {
        if (!(is_object($this->hostlib) && $this->hostlib instanceof LocalHost)) {
            $this->hostlib = new LocalHost();
        }
        $host = $this->hostlib;
        $cwd = $this->instance->webroot;

        // change cwd before executing commands, for instance in CoreOS it may influence what
        // php interpreter version is used to execute commands, if the dir is not available
        // try the parent directory
        if ($this->changeLocation === null && !empty($cwd)) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                if (chdir($cwd)) {
                    $this->changeLocation = $cwd;
                } elseif (chdir(dirname($cwd))) {
                    $this->changeLocation = dirname($cwd);
                }
            } else {
                if ($this->fileExists($cwd)) {
                    $this->changeLocation = $cwd;
                } elseif ($this->fileExists(dirname($cwd))) {
                    $this->changeLocation = dirname($cwd);
                }
                if ($this->changeLocation === null) {
                    $this->changeLocation = false;
                }
            }
        }
        if ($this->changeLocation) {
            $host->chdir($this->changeLocation);
        }

        return $host;
    }

    public function firstConnect()
    {
        return true;
    }

    public function getInterpreterPath($instance2 = null)
    {
        $host = $this->getHost();

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $attempts = [
                "where php.exe",
                "where php5.exe",
                "where php7.exe"
            ];
        } else {
            $attempts = [
                'command -v php 2>/dev/null',
                "command php -r \"defined('PHP_BINARY') && print(PHP_BINARY);\"",
                "command php7 -r \"defined('PHP_BINARY') && print(PHP_BINARY);\"",
                "command php5 -r \"defined('PHP_BINARY') && print(PHP_BINARY);\"",
                "which php4"
            ];
        }

        // Get possible paths
        $phps = $host->runCommands($attempts);
        $phps = explode("\n", $phps);

        // Check different versions
        $valid = [];
        foreach ($phps as $interpreter) {
            if ('php' !== substr(basename($interpreter), 0, 3)) {
                continue;
            }

            $versionInfo = $host->runCommands("$interpreter -v");
            if (preg_match('/PHP (\d+\.\d+\.\d+)/', $versionInfo, $matches)) {
                if (empty($valid[$matches[1]])) {
                    $valid[$matches[1]] = $interpreter;
                }
            }
        }

        if (count($valid) == 1) {
            return reset($valid);
        }

        // List available options for user
        echo "Multiple PHP interpreters available on host :\n";
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

    public function getSVNPath()
    {
        $host = $this->getHost();

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $svn_name='svn.exe';
            $sets = [
                ['where svn'],
            ];
        } else {
            $svn_name='svn';
            $sets = [
                ['which svn'],
            ];
        }

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

                if (strpos($interpreter, ' ') !== false) {
                    $interpreter = '"' . trim($interpreter) . '"'; // wrap command if contains spaces
                }

                $versionInfo = $host->runCommands("$interpreter --version");
                if (preg_match("/svn, version (\d+\.\d+\.\d+)/", $versionInfo, $matches)) {
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
        $versionInfo = $host->runCommands("$interpreter -r \"echo PHP_VERSION_ID;\"");
        return $versionInfo;
    }

    public function getDistributionName($interpreter)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return "Windows";
        }

        $host = $this->getHost();
        $command = file_get_contents(
            sprintf('%s/../getlinuxdistro.php', dirname(__FILE__))
        );
        $linuxName = $host->runCommands("$interpreter -r \"$command\"");

        return $linuxName;
    }

    public function fileExists($filename)
    {
        if (!$this->isLocalPath($filename)) {
            $filename = $this->instance->getWebPath($filename);
        }

        return file_exists($filename);
    }

    public function fileGetContents($filename)
    {
        if (!$this->isLocalPath($filename)) {
            $filename = $this->instance->getWebPath($filename);
        }

        return @file_get_contents($filename);
    }

    public function fileModificationDate($filename)
    {
        if (!$this->isLocalPath($filename)) {
            $filename = $this->instance->getWebPath($filename);
        }

        return date("Y-m-d", filemtime($filename));
    }

    public function runPHP($localFile, $args = [])
    {
        $host = $this->getHost();

        $remoteName = md5($localFile);
        $remoteFile = $this->instance->getWorkPath($remoteName);

        $host->sendFile($localFile, $remoteFile);
        $arg = implode(' ', array_map('escapeshellarg', $args));
        $output = $host->runCommands("{$this->instance->phpexec} -d memory_limit=256M {$remoteFile} {$arg}");
        if (file_exists($remoteFile)) {
            unlink($remoteFile);
        }

        return $output;
    }

    public function downloadFile($filename, $dest = '')
    {
        if (!$this->isLocalPath($filename)) {
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
        if ($this->isLocalPath($remoteLocation)) {
            $host->sendFile($filename, $remoteLocation);
        } else {
            $host->sendFile($filename, $this->instance->getWebPath($remoteLocation));
        }
    }

    public function deleteFile($filename)
    {
        preg_match('/^([a-zA-Z]\:[\/,\\\\]).{1,}/', $filename, $matches);

        if ($filename{0} != '/' && empty($matches)) {
            $filename = $this->instance->getWebPath($filename);
        }

        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    public function moveFile($remoteSource, $remoteTarget)
    {
        if (!$this->isLocalPath($remoteSource)) {
            $remoteSource = $this->instance->getWebPath($remoteSource);
        }
        if (!$this->isLocalPath($remoteTarget)) {
            $remoteTarget = $this->instance->getWebPath($remoteTarget);
        }

        rename($remoteSource, $remoteTarget);
    }

    public function copyFile($remoteSource, $remoteTarget)
    {
        if (!$this->isLocalPath($remoteSource)) {
            $remoteSource = $this->instance->getWebPath($remoteSource);
        }
        if (!$this->isLocalPath($remoteTarget)) {
            $remoteTarget = $this->instance->getWebPath($remoteTarget);
        }

        copy($remoteSource, $remoteTarget);
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
        if (! is_array($commands)) {
            $commands = func_get_args();
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
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $exists = $this->shellExec("where $command");
        } else {
            $exists = $this->shellExec("which $command");
        }

        return ! empty($exists);
    }

    public function localizeFolder($remoteLocation, $localMirror)
    {
        $host = $this->getHost();
        if (ApplicationHelper::isWindows()) {
            $localMirror .= DIRECTORY_SEPARATOR . basename($remoteLocation);
            $result = $host->windowsSync(
                $remoteLocation,
                $localMirror,
                null,
                ['.svn/tmp']
            );

            return $result > 8 ? $result : 0;
        } else {
            return $host->rsync([
                'src' => $remoteLocation,
                'dest' => $localMirror,
                'link-dest' => dirname($remoteLocation)
            ]);
        }
    }
}
