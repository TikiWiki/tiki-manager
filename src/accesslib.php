<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

define('SQL_SELECT_ACCESS', '
SELECT
    rowid, type, host, user, pass password
FROM
    access
WHERE
    instance_id = :id
;');

define('SQL_INSERT_ACCESS', "
INSERT OR REPLACE INTO
    access
(instance_id, rowid, type, user, host, pass)
    VALUES
(:instance, :rowid, :type, :user, (:host || ':' || :port), :pass)
;");

abstract class Access
{
    private $rowid;
    private $type;
    protected $instance;

    public $host;
    public $user;
    public $password;
    public $port;

    function __construct(Instance $instance, $type)
    {
        $this->instance = $instance;
        $this->type = $type;
    }

    static function getClassFor($type)
    {
        $types = array(
            'ftp'        => 'Access_FTP',
            'local'      => 'Access_Local',
            'ssh::nokey' => 'Access_SSH',
            'ssh'        => 'Access_SSH',
        );

        if (!empty($types[$type])) {
            return $types[$type];
        }

        throw new Exception("Unknown type: $type", 1);
    }

    static function getAccessFor(Instance $instance)
    {
        $result = query(SQL_SELECT_ACCESS, array(':id' => $instance->id));

        $access = array();
        while ($row = $result->fetch()) {
            $class = self::getClassFor($row['type']);

            $a = new $class($instance);

            if ($row['type'] != 'local') {
                list($a->host, $a->port) = explode(':', $row['host']);
                $a->user = $row['user'];
                $a->password = $row['password'];
            }

            $access[] = $a;
        }

        return $access;
    }
    
    function save()
    {
        $params = array(
            ':instance' => $this->instance->id,
            ':rowid' => $this->rowid,
            ':type' => $this->type,
            ':host' => $this->host,
            ':user' => $this->user,
            ':pass' => $this->password,
            ':port' => $this->port,
        );

        query(SQL_INSERT_ACCESS, $params);

        $rowid = rowid();
        if (! $this->rowid && $rowid)
            $this->rowid = $rowid;
    }

    function changeType( $type )
    {
        if (strpos($type, "{$this->type}::") === false) {
            $this->type = $type;
            return true;
        }
        else
            return false;
    }

    abstract function firstConnect();

    abstract function getInterpreterPath($instance2 = null);

    abstract function fileExists($filename);

    abstract function fileGetContents($filename);

    abstract function fileModificationDate($filename);

    abstract function runPHP($localFile, $args = array());

    abstract function downloadFile($filename);

    abstract function uploadFile($filename, $remoteLocation);

    abstract function deleteFile($filename);

    abstract function moveFile($remoteSource, $remoteTarget);
    
    abstract function copyFile($remoteSource, $remoteTarget);

    abstract function localizeFolder($remoteLocation, $localMirror);
}

interface ShellPrompt
{
    function shellExec($command);

    function openShell($workingDir = '');

    function chdir($location);

    function setenv($var, $value);

    function hasExecutable($name);

    function createCommand($bin, $args=array(), $stdin='');

    function runCommand($command, $options=array());
}

interface Mountable
{
    function mount($target);
    function umount();
    function synchronize($source, $mirror, $keepFolderName = false);
}

class Access_Local extends Access implements ShellPrompt
{
    private $location;
    private $env = array();
    private $hostlib = null;
    private $changeLocation = null;

    function __construct(Instance $instance)
    {
        parent::__construct($instance, 'local');
    }

    private function isLocalPath($pathname) {
        if($pathname{0} == '/') return true;

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && preg_match("|^[a-zA-Z]:[/\\\\]|", $pathname))
            return true;

        return false;
    }

    public function getHost()
    {
        if(!(is_object($this->hostlib) && $this->hostlib instanceof Local_Host)){
            $this->hostlib = new Local_Host();
        }
        $host = $this->hostlib;

        // change cwd before executing commands, for instance in CoreOS it may influence what
        // php interpreter version is used to execute commands, if the dir is not available
        // try the parent directory
        if ($this->changeLocation === null && !empty($this->instance->webroot)) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                if(chdir($this->instance->webroot))
                    $this->changeLocation = $this->instance->webroot;
                else if(chdir(dirname($this->instance->webroot)))
                        $this->changeLocation = dirname($this->instance->webroot);
            } else {
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
        }
        if ($this->changeLocation) {
            $host->chdir($this->changeLocation);
        }

        return $host;
    }

    function firstConnect()
    {
        return true;
    }

    function getInterpreterPath($instance2 = null)
    {
        $host = $this->getHost();

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $attempts = array(
                "where php.exe",
                "where php5.exe",
                "where php7.exe"
            );
        } else {
            $attempts = array(
                'command -v php 2>/dev/null',
                "command php -r \"defined('PHP_BINARY') && print(PHP_BINARY);\"",
                "command php7 -r \"defined('PHP_BINARY') && print(PHP_BINARY);\"",
                "command php5 -r \"defined('PHP_BINARY') && print(PHP_BINARY);\"",
                "which php4"
            );
        }

        // Get possible paths
        $phps = $host->runCommands($attempts);
        $phps = explode("\n", $phps);

        // Check different versions
        $valid = array();
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

        if (count($valid) == 1)
            return reset($valid);

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
        while (! array_key_exists($selection, $versions))
            $selection = readline("Which version do you want to use? (0-$counter) : ");

        $version = $versions[$selection];
        return $valid[$version];
    }

    function getSVNPath()
    {
        $host = $this->getHost();
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $svn_name='svn.exe';
            $sets = array(
                array('where svn'),
            );
        } else {
            $svn_name='svn';
            $sets = array(
                array('which svn'),
            );
        }

        foreach ($sets as $attempt) {
            // Get possible paths
            $svns = $host->runCommands($attempt);
            $svns = explode("\n", $svns);

            // Check different versions
            $valid = array();
            foreach ($svns as $interpreter) {
                if (! in_array(basename($interpreter), array($svn_name)))
                    continue;

                if (strpos($interpreter, ' ') !== false) {
                    $interpreter = '"' . trim($interpreter) . '"'; // wrap command if contains spaces
                }

                $versionInfo = $host->runCommands("$interpreter --version");
                if (preg_match("/svn, version (\d+\.\d+\.\d+)/", $versionInfo, $matches))
                    $valid[$matches[1]] = $interpreter;
            }

            // Handle easy cases
            if (count($valid) == 0)
                continue;
            if (count($valid) == 1)
                return reset($valid);

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
            while (! array_key_exists($selection, $versions))
                $selection = readline("Which version do you want to use? (0-$counter) : ");

            $version = $versions[$selection];
            return $valid[$version];
        }
    }

    function getInterpreterVersion($interpreter)
    {
        $host = $this->getHost();
        $versionInfo = $host->runCommands("$interpreter -r \"echo PHP_VERSION_ID;\"");
        return $versionInfo;
    }

    function getDistributionName($interpreter){
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            return "Windows";

        $host = $this->getHost();
        $command = file_get_contents(
            sprintf('%s/getlinuxdistro.php', dirname(__FILE__)));
        $linuxName = $host->runCommands("$interpreter -r \"$command\"");

        return $linuxName;
    }

    function fileExists($filename)
    {
        if (!$this->isLocalPath($filename))
            $filename = $this->instance->getWebPath($filename);

        return file_exists($filename);
    }

    function fileGetContents($filename)
    {
        return @file_get_contents($filename);
    }

    function fileModificationDate($filename)
    {
        return date("Y-m-d", filemtime($filename));
    }

    function runPHP($localFile, $args = array())
    {
        $host = $this->getHost();

        $remoteName = md5($localFile);
        $remoteFile = $this->instance->getWorkPath($remoteName);

        $host->sendFile($localFile, $remoteFile);
        $arg = implode(' ', array_map('escapeshellarg', $args));
        $output = $host->runCommands("{$this->instance->phpexec} -d memory_limit=256M {$remoteFile} {$arg}");
        unlink($remoteFile);

        return $output;
    }

    function downloadFile($filename)
    {
        if (!$this->isLocalPath($filename))
            $filename = $this->instance->getWebPath($filename);

        $dot = strrpos($filename, '.');
        $ext = substr($filename, $dot);

        $local = tempnam(TEMP_FOLDER, 'trim');

        $host = $this->getHost();
        $host->receiveFile($filename, $local);

        rename($local, $local . $ext);
        chmod($local . $ext, 0644);

        return $local . $ext;
    }

    function uploadFile($filename, $remoteLocation)
    {
        $host = $this->getHost();
        if ($this->isLocalPath($remoteLocation))
            $host->sendFile($filename, $remoteLocation);
        else
            $host->sendFile($filename, $this->instance->getWebPath($remoteLocation));
    }

    function deleteFile($filename)
    {
        if ($filename{0} != '/')
            $filename = $this->instance->getWebPath($filename);

        unlink($filename);
    }

    function moveFile($remoteSource, $remoteTarget)
    {
        if (!$this->isLocalPath($remoteSource))
            $remoteSource = $this->instance->getWebPath($remoteSource);
        if (!$this->isLocalPath($remoteTarget))
            $remoteTarget = $this->instance->getWebPath($remoteTarget);

        rename($remoteSource, $remoteTarget);
    }

    function copyFile($remoteSource, $remoteTarget)
    {
        if (!$this->isLocalPath($remoteSource))
            $remoteSource = $this->instance->getWebPath($remoteSource);
        if (!$this->isLocalPath($remoteTarget))
            $remoteTarget = $this->instance->getWebPath($remoteTarget);

        copy($remoteSource, $remoteTarget);
    }

    function chdir($location)
    {
        $this->location = $location;
    }

    function setenv($var, $value)
    {
        $this->env[$var] = $value;
    }

    function shellExec($commands, $output = false)
    {
        if (! is_array($commands))
            $commands = func_get_args();

        $host = $this->getHost();
        if ($this->location)
            $host->chdir($this->location);
        foreach ($this->env as $key => $value)
            $host->setenv($key, $value);

        return $host->runCommands($commands, $output);
    }

    function createCommand($bin, $args=array(), $stdin='')
    {
        $options = array();

        if ($this->location) {
            $options['cwd'] = $this->location;
        }
        if ($this->env) {
            $options['env'] = $this->env;
        }

        $command = new Host_Command($bin, $args, $stdin);
        $command->setOptions($options);
        $command->setHost($this->getHost());
        return $command;
    }

    function runCommand($command, $options=array())
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

    function openShell($workingDir = '')
    {
        $host = $this->getHost();
        $host->openShell($workingDir);
    }

    function hasExecutable($command)
    {
        $command = escapeshellcmd($command);
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $exists = $this->shellExec("where $command");
        } else {
            $exists = $this->shellExec("which $command");
        }

        return ! empty($exists);
    }

    function localizeFolder($remoteLocation, $localMirror)
    {
        $host = $this->getHost();
        return $host->rsync(array(
            'src' => $remoteLocation, 
            'dest' => $localMirror
        ));
    }
}

class Access_SSH extends Access implements ShellPrompt
{
    private $location;
    private $env = array();
    private $changeLocation = null;

    function __construct(Instance $instance)
    {
        parent::__construct($instance, 'ssh');
        $this->port = 22;
    }

    public function getHost()
    {
        $host = new SSH_Host($this->host, $this->user, $this->port);

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

    function firstConnect()
    {
        $host = $this->getHost();
        $host->setupKey(SSH_PUBLIC_KEY);

        info("Testing connection...");

        $host->runCommands('exit');

        $answer = promptUser(
            'After successfully entering your password, were you asked for a password again?',
            false, array('yes', 'no')
        );

        if ($answer == 'yes')
            $this->changeType('ssh::nokey');

        return true;
    }

// FIXME: Expect all remote to be Unix-like machines
    function getInterpreterPath($instance2 = null)
    {
        $host = $this->getHost();
        
        $sets = array(
            array('which php', 'which php5', 'which php4'),
        );
        $php_name = array('php', 'php5');

        foreach ($sets as $attempt) {

            // Get possible paths
            $phps = $host->runCommands($attempt);
            $phps = explode("\n", $phps);

            // Check different versions
            $valid = array();
            foreach ($phps as $interpreter) {
                if (! in_array(basename($interpreter), array('php', 'php5')))
                    continue;

                $versionInfo = $host->runCommands("$interpreter -v");
                if (preg_match('/PHP (\d+\.\d+\.\d+)/', $versionInfo, $matches))
                    $valid[$matches[1]] = $interpreter;
            }

            // Handle easy cases
            if (count($valid) == 0)
                continue;
            if (count($valid) == 1)
                return reset($valid);

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
            while (! array_key_exists($selection, $versions))
                $selection = readline("Which version do you want to use? (0-$counter) : ");

            $version = $versions[$selection];
            return $valid[$version];
        }
    }

    function getSVNPath()
    {
        $host = $this->getHost();
        
        $sets = array(
                array('which svn'),
        );
        $svn_name='svn';

        foreach ($sets as $attempt) {
            // Get possible paths
            $svns = $host->runCommands($attempt);
            $svns = explode("\n", $svns);

            // Check different versions
            $valid = array();
            foreach ($svns as $interpreter) {
                if (! in_array(basename($interpreter), array($svn_name)))
                    continue;

                $versionInfo = $host->runCommands("$interpreter --version");
                if (preg_match('/svn, version (\d+\.\d+\.\d+)/', $versionInfo, $matches))
                    $valid[$matches[1]] = $interpreter;
            }

            // Handle easy cases
            if (count($valid) == 0)
                continue;
            if (count($valid) == 1)
                return reset($valid);

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
            while (! array_key_exists($selection, $versions))
                $selection = readline("Which version do you want to use? (0-$counter) : ");

            $version = $versions[$selection];
            return $valid[$version];
        }
    }

    function getInterpreterVersion($interpreter)
    {
        $host = $this->getHost();
        $versionInfo = $host->runCommands("$interpreter -r 'echo PHP_VERSION_ID;'");
        return $versionInfo;
    }

    function getDistributionName($interpreter){
        $host = $this->getHost();
        $command = file_get_contents(
            sprintf('%s/getlinuxdistro.php', dirname(__FILE__)));
        $linuxName = $host->runCommands("$interpreter -r '$command'");

        return $linuxName;
    }

    function fileExists($filename)
    {
        if ($filename{0} != '/') {
            $filename = $this->instance->getWebPath($filename);
        }
        $command = $this->createCommand('test', array('-f', $filename));
        return $command->run()->getReturn() === 0;
    }

    function fileGetContents($filename)
    {
        $host = $this->getHost();
        $filename = escapeshellarg($filename);

        return $host->runCommands("cat $filename");
    }

    function fileModificationDate($filename)
    {
        $host = $this->getHost();
        $root = escapeshellarg($filename);
        $data = $host->runCommands("ls -l $root");

        if (preg_match('/\d{4}-\d{2}-\d{2}/', $data, $matches))
            return $matches[0];
        else
            return null;
    }

    function runPHP($localFile, $args = array())
    {
        $host = $this->getHost();

        $remoteName = md5($localFile);
        $remoteFile = $this->instance->getWorkPath($remoteName);
        $host->runCommands(
            'mkdir -p ' . (escapeshellarg($this->instance->tempdir) ?: TRIM_TEMP)
        );

        $host->sendFile($localFile, $remoteFile);
        $arg = implode(' ', array_map('escapeshellarg', $args));
        $output = $host->runCommands(
            "{$this->instance->phpexec} -q -d memory_limit=256M {$remoteFile} {$arg}",
            "rm {$remoteFile}"
        );

        return $output;
    }

    function downloadFile($filename)
    {
        if ($filename{0} != '/')
            $filename = $this->instance->getWebPath($filename);

        $dot = strrpos($filename, '.');
        $ext = substr($filename, $dot);

        $local = tempnam(TEMP_FOLDER, 'trim');

        $host = $this->getHost();
        $host->receiveFile($filename, $local);

        rename($local, $local . $ext);
        chmod($local . $ext, 0644 );

        return $local . $ext;
    }

    function uploadFile( $filename, $remoteLocation )
    {
        $host = $this->getHost();
        if ($remoteLocation{0} == '/' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            $host->sendFile($filename, $remoteLocation);
        else
            $host->sendFile($filename, $this->instance->getWebPath($remoteLocation));
    }

    function deleteFile($filename)
    {
        if ($filename{0} != '/' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            $filename = $this->instance->getWebPath($filename);

        $path = escapeshellarg($filename);

        $host = $this->getHost();
        $host->runCommands("rm $path");
    }

    function moveFile($remoteSource, $remoteTarget)
    {
        if ($remoteSource{0} != '/' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')
            $remoteSource = $this->instance->getWebPath($remoteSource);
        if ($remoteTarget{0} != '/' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')
            $remoteTarget = $this->instance->getWebPath($remoteTarget);

        $a = escapeshellarg($remoteSource);
        $b = escapeshellarg($remoteTarget);

        $this->shellExec("mv $a $b");
    }

    function copyFile($remoteSource, $remoteTarget)
    {
        if ($remoteSource{0} != '/' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')
            $remoteSource = $this->instance->getWebPath($remoteSource);
        if ($remoteTarget{0} != '/' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')
            $remoteTarget = $this->instance->getWebPath($remoteTarget);

        $a = escapeshellarg($remoteSource);
        $b = escapeshellarg($remoteTarget);

        $this->shellExec("cp $a $b");
    }

    function chdir($location)
    {
        $this->location = $location;
    }

    function setenv($var, $value)
    {
        $this->env[$var] = $value;
    }

    function shellExec($commands, $output=false)
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
        if ($this->location)
            $host->chdir($this->location);

        foreach ($this->env as $key => $value)
            $host->setenv($key, $value);

        return $host->runCommands($commands, $output);
    }

    function createCommand($bin, $args=array(), $stdin='')
    {
        $options = array();

        if ($this->location) {
            $options['cwd'] = $this->location;
        }
        if ($this->env) {
            $options['env'] = $this->env;
        }

        $command = new Host_Command($bin, $args, $stdin);
        $command->setOptions($options);
        $command->setHost($this->getHost());
        return $command;
    }

    function runCommand($command, $options=array())
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

    function openShell($workingDir = '')
    {
        $host = $this->getHost();
        $host->openShell($workingDir);
    }

    function hasExecutable($command)
    {
        $command = escapeshellcmd($command);
        $exists = $this->shellExec("which $command");

        return ! empty($exists);
    }

    function localizeFolder($remoteLocation, $localMirror)
    {
        $host = $this->getHost();
        return $host->rsync(array(
            'src' => $remoteLocation,
            'dest' => $localMirror
        ));
    }
}

class Access_FTP extends Access implements Mountable
{
    private $lastMount;

    function __construct(Instance $instance)
    {
        parent::__construct($instance, 'ftp');
        $this->port = 21;
    }

    // TODO: change directory using FTP
    function openShell($workingDir = '')
    {
        echo "User: {$this->user}, Pass: {$this->password}\n";
        passthru("ftp {$this->host} {$this->port}");
    }

    public function getHost()
    {
        return new FTP_Host($this->host, $this->user, $this->password, $this->port);
    }

    function firstConnect()
    {
        $conn = $this->getHost();

        return $conn->connect();
    }

    function getInterpreterPath($instance2 = null)
    {
        if ($instance2 instanceof Instance)
            $this->instance = $instance2;

        $result = $this->runPHP(
            dirname(__FILE__) . '/../scripts/checkversion.php',
            array($this->instance->webroot)
        );

        if (preg_match('/^[5-9]\./', $result))
            return 'mod_php';
    }

    function getSVNPath(){
        return 1;
    }

    function getInterpreterVersion($interpreter)
    {
        return 99999;
    }

    function getDistributionName($interpreter){
        return 'Unknown';
    }

    function fileExists($filename)
    {
        if ($filename{0} != '/')
            $filename = $this->instance->getWebPath($filename);

        $ftp = $this->getHost();
        return $ftp->fileExists($filename);
    }

    function fileGetContents($filename)
    {
        $ftp = $this->getHost();
        return $ftp->getContent($filename);
    }

    function fileModificationDate($filename)
    {
    }

    function runPHP($localFile, $args = array())
    {
        foreach ($args as & $potentialPath) {
            if ($potentialPath{0} == '/') {
                $potentialPath = $this->obtainRelativePathTo(
                    $potentialPath, $this->instance->webroot
                );
            }
        }
        $host = $this->getHost();

        $remoteName = 'trim_' . md5($localFile) . '.php';
        $remoteFile = $this->instance->getWebPath($remoteName);

        array_unshift($args, null);
        $arg = http_build_query($args, '', '&');

        $host->sendFile($localFile, $remoteFile);
        $output = file_get_contents($this->instance->getWebUrl($remoteName) . '?' . $arg);

        $host->removeFile($remoteFile);

        return $output;
    }

    function downloadFile($filename)
    {
        if ($filename{0} != '/')
            $filename = $this->instance->getWebPath($filename);

        $dot = strrpos($filename, '.');
        $ext = substr($filename, $dot);

        $local = tempnam(TEMP_FOLDER, 'trim');

        $host = $this->getHost();
        $host->receiveFile($filename, $local);

        rename($local, $local . $ext);
        chmod($local . $ext, 0644);

        return $local . $ext;
    }

    function uploadFile($filename, $remoteLocation)
    {
        $host = $this->getHost();
        if ($remoteLocation{0} == '/')
            $host->sendFile($filename, $remoteLocation);
        else
            $host->sendFile($filename, $this->instance->getWebPath($remoteLocation));
    }

    function moveFile($remoteSource, $remoteTarget)
    {
        if ($remoteSource{0} != '/')
            $remoteSource = $this->instance->getWebPath($remoteSource);
        if ($remoteTarget{0} != '/')
            $remoteTarget = $this->instance->getWebPath($remoteTarget);

        $host = $this->getHost();
        $host->rename($remoteSource, $remoteTarget);
    }

    function copyFile($remoteSource, $remoteTarget)
    {
        if ($remoteSource{0} != '/')
            $remoteSource = $this->instance->getWebPath($remoteSource);
        if ($remoteTarget{0} != '/')
            $remoteTarget = $this->instance->getWebPath($remoteTarget);

        $host = $this->getHost();
        $host->copy($remoteSource, $remoteTarget);
    }

    function deleteFile($filename)
    {
        if ($filename{0} != '/')
            $filename = $this->instance->getWebPath($filename);

        $host = $this->getHost();
        $host->removeFile($filename);
    }

    function localizeFolder($remoteLocation, $localMirror)
    {
        if ($remoteLocation{0} != '/')
            $remoteLocation = $this->instance->getWebPath($remoteLocation);

        $compress = in_array('zlib', $this->instance->getExtensions());

        $name = md5(time()) . '.tar';
        if ($compress) $name .= '.gz';

        $remoteTar = $this->instance->getWebPath($name);
        $this->runPHP(
            dirname(__FILE__) . '/../scripts/package_tar.php',
            array($remoteTar, $remoteLocation)
        );

        $localized = $this->downloadFile($remoteTar);
        $this->deleteFile($remoteTar);

        $current = getcwd();
        if (! file_exists($localMirror))
            mkdir($localMirror);
        
        chdir($localMirror);

        $eLoc = escapeshellarg($localized);
        if ($compress)
            passthru("tar -zxf $eLoc");
        else
            `tar -xf $eLoc`;

        chdir($current);
    }

    static function obtainRelativePathTo($targetFolder, $originFolder)
    {
        $parts = array();
        while ((0 !== strpos($targetFolder, $originFolder)) 
            && $originFolder != '/' && $originFolder != '') {
            $originFolder = dirname($originFolder);
            $parts[] = '..';
        }

        $out = null;
        if (strpos($targetFolder, $originFolder) === false) {
            // Target is under the origin
            $relative = substr($targetFolder, strlen($originFolder));
            $out = ltrim(implode('/', $parts) . '/' . ltrim($relative, '/' ), '/');
        }

        if (empty($out)) $out = '.';

        return $out;
    }

    function mount($target)
    {
        if ($this->lastMount)
            return false;

        $ftp = $this->getHost();
        $pwd = $ftp->getPWD();
        $toRoot = preg_replace('/\w+/', '..', $pwd);

        $this->lastMount = $target;

        $remote = escapeshellarg("ftp://{$this->user}:{$this->password}@{$this->host}$toRoot");
        $local = escapeshellarg($target);

        $cmd = "curlftpfs $remote $local";
        shell_exec($cmd);

        return true;
    }

    function umount()
    {
        if ($this->lastMount) {
            $loc = escapeshellarg($this->lastMount);
            `sudo umount $loc`;
            $this->lastMount = null;
        }
    }

    function synchronize($source, $mirror, $keepFolderName = false)
    {
        $source = rtrim($source, '/') . ($keepFolderName ? '' : '/');
        $mirror = rtrim($mirror, '/') . '/';

        $source = escapeshellarg($source);
        $target = escapeshellarg($mirror);
        $tmp = escapeshellarg(RSYNC_FOLDER);
        $cmd = 'rsync -rDu --no-p --no-g --size-only ' .
            '--exclude .svn --exclude copyright.txt --exclude changelog.txt ' .
            "--temp-dir=$tmp $source $target";
        passthru($cmd);
    }

    function copyLocalFolder($localFolder, $remoteFolder = '')
    {
        if ($remoteFolder{0} != '/')
            $remoteFolder = $this->instance->getWebPath($remoteFolder);

        $compress = in_array('zlib', $this->instance->getExtensions());

        $current = getcwd();
        chdir($localFolder);

        $temp = TEMP_FOLDER;
        $name = md5(time()) . '.tar';
        `chmod 777 db`;
        `tar --exclude=.svn -cf $temp/$name *`;
        if ($compress) {
            `gzip -5 $temp/$name`;
            $name .= '.gz';
        }

        chdir($current);

        $this->uploadFile("$temp/$name", $name);
        unlink("$temp/$name");

        $this->runPHP(
            dirname(__FILE__) . '/../scripts/extract_tar.php',
            array($name, $remoteFolder)
        );

        $this->deleteFile($name);
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
