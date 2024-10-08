<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Access;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Application\Tiki\Versions\TikiRequirements;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\Environment as Env;
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
        if ($pathname[0] == '/') {
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

    /**
     * @param TikiRequirements|null $requirements
     * @return false|mixed
     * @throws \Exception When no PHP interpreter was found in the system
     */
    public function getInterpreterPath(TikiRequirements $requirements = null)
    {
        $instance = $this->instance;
        $detectedBinaries = $instance->phpexec ? [$instance->phpexec] : $instance->getDiscovery()->detectPHP();

        $valid = [];

        foreach ($detectedBinaries as $binary) {
            try {
                $version = $this->getInterpreterVersion($binary);
            } catch (\Exception $e) {
                continue;
            }

            $formattedVersion = CommandHelper::formatPhpVersion($version);
            if (($version >= 50300 && !$requirements) || ($requirements && $requirements->getPhpVersion()->isValidVersion($formattedVersion))) {
                $valid[$formattedVersion] = $binary;
            }
        }

        if (count($valid) == 1) {
            return reset($valid);
        }

        // Instance current PHPExec no longer valid, re-detect again!
        if ($instance->phpexec) {
            $instance->phpexec = null;
            return $this->getInterpreterPath($requirements);
        }

        if (empty($valid)) {
            throw new \Exception("No suitable php interpreter was found on {$instance->name} instance");
        }

        // Assume that the first in the list should be the default one;
        $defaultVersion = key($valid);

        // List available options for user
        krsort($valid);

        $question = 'Multiple PHP interpreters available on host, which version do you want to use?';
        $options = array_keys($valid);
        $pickedVersion = $this->io->choice($question, $options, $defaultVersion);

        return $valid[$pickedVersion];
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
            $this->io->writeln("Multiple SVN'es available on host :");
            $counter = 0;
            krsort($valid);
            $versions = array_keys($valid);
            foreach ($valid as $version => $path) {
                $this->io->writeln("[$counter] $path ($version)");
                $counter++;
            }

            // Ask user
            $counter--;
            $selection = -1;
            while (! array_key_exists($selection, $versions)) {
                $selection = $this->io->ask("Which version do you want to use? (0-$counter) : ");
            }

            $version = $versions[$selection];
            return $valid[$version];
        }
    }

    public function getInterpreterVersion($interpreter)
    {
        return $this->instance->getDiscovery()->detectPHPVersion($interpreter);
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
        $linuxName = $host->runCommands("$interpreter -r '$command'");

        return $linuxName;
    }

    public function createDirectory($path)
    {
        $fs = new Filesystem();

        try {
            $fs->mkdir($path);
        } catch (IOException $e) {
            return false;
        }

        return true;
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

    /**
     * @param $filename
     * @param string $target
     * @return string
     */
    public function downloadFile($filename, $target = ''): string
    {
        if (!$this->isLocalPath($filename)) {
            $filename = $this->instance->getWebPath($filename);
        }

        $dot = strrpos($filename, '.');
        $ext = substr($filename, $dot);

        $tempFolder = Env::get('TEMP_FOLDER');
        $local = $target ?: tempnam($tempFolder, 'trim');

        $this->getHost()->receiveFile($filename, $local);

        if (!$target) {
            $target = $local . $ext;
            rename($local, $target);
            chmod($target, 0644);
        }

        return $target;
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

        if ($filename[0] != '/' && empty($matches)) {
            $filename = $this->instance->getWebPath($filename);
        }

        if (file_exists($filename)) {
            @unlink($filename);
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

        return $command->run($host, $options);
    }

    public function openShell($workingDir = '')
    {
        $host = $this->getHost();
        return $host->openShell($workingDir);
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
            if (! file_exists($remoteLocation)) {
                // certain storage folders might not exist, so we have nothing to sync
                return 0;
            } else {
                return $host->rsync([
                    'src' => $remoteLocation,
                    'dest' => $localMirror,
                    'link-dest' => dirname($remoteLocation)
                ]);
            }
        }
    }

    public function isEmptyDir($path): bool
    {
        $dirContents = scandir($path);
        $dirContents = array_diff($dirContents, ['.','..']);

        return empty($dirContents);
    }

    /**
     * Reducing the priority of operations to minimize their impact on system performance.
     * Adjusts the priority of a command using 'nice' and 'ionice' if available.
     *
     * @param string|empty $cmd The command to execute.
     * @return string The command potentially modified with 'nice' and 'ionice'.
     */
    public function executeWithPriorityParams($cmd = '')
    {
        // Check if the local system is Linux since 'nice' and 'ionice' are Unix/Linux commands
        $localOS = strtolower(trim($this->shellExec('uname -s', true)));
        $isLocalUnix = $localOS === 'linux';

        if ($isLocalUnix) {
            // Check for 'nice' and 'ionice' availability
            $hasNice = trim($this->shellExec('which nice', true));
            $hasIonice = trim($this->shellExec('which ionice', true));
             // Modify the command based on the availability of 'nice' and 'ionice'
            if (!empty($hasNice) && !empty($hasIonice)) {
                $cmd = 'nice -n 19 ionice -c2 -n7 ' . $cmd;
            } elseif (!empty($hasNice)) {
                $cmd = 'nice -n 19 ' . $cmd;
            } elseif (!empty($hasIonice)) {
                $cmd = 'ionice -c2 -n7 ' . $cmd;
            }
        }

        return $cmd;
    }
}
