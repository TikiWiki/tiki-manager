<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application;

use TikiManager\Access\Access;
use TikiManager\Application\Exception\ConfigException;

class Discovery
{
    protected $access;
    protected $instance;
    protected $config = [];

    protected $distroProbes = [
        "Arch"    => ["release" => "arch-release",    "regex" => null],
        "Ubuntu"  => ["release" => "issue",           "regex" => "/^Ubuntu/"],
        "Debian"  => ["release" => "debian_version",  "regex" => null],
        "Fedora"  => ["release" => "fedora-release",  "regex" => null],
        "ClearOS" => ["release" => "clearos-release", "regex" => null],
        "CentOS"  => ["release" => "centos-release",  "regex" => null],
        "Mageia"  => ["release" => "mageia-release",  "regex" => null],
        "Redhat"  => ["release" => "redhat-release",  "regex" => null]
    ];

    public function __construct($instance, $access = null)
    {
        $this->setInstance($instance);
        $this->setAccess($access);
    }

    public function detectBackupPerm()
    {
        $os = $this->getConf('os') ?: $this->detectOS();
        $user = $this->getConf('user') ?: $this->detectUser();
        $distro = $this->getConf('distro') ?: $this->detectDistro();

        if ($os === 'WINDOWS' || $os === 'WINNT') {
            return ['Administrator', 'Administrator', 0750];
        }

        if ($distro === 'ClearOS') {
            return [$user, 'allusers', 0750];
        }

        return [$user, $user, 0750];
    }

    public function detectDistro()
    {
        $distro = null;

        $os = !empty($this->config['os']) ? $this->config['os'] : $this->detectOS();

        if ($os == 'DARWIN') {
            $distro = 'OSX';
        }

        if (substr($os, 0, 3) === 'WIN') {
            $distro = 'Windows';
        }

        // attempt 1: check distro on modern Linux (>= 2012)
        $found = $this->detectDistroSystemd();
        if ($found) {
            if (isset($this->distroProbes[$found])) {
                $distro = $found;
            }
        }

        // attempt 2: check by files we know
        if (empty($distro)) {
            $distro = $this->detectDistroByProbing();
        }

        // fallback: when found but not recognized on attempt 1 and failed on attempt 2
        if (empty($distro) && $found) {
            $distro = $found;
        }

        $this->config['distro'] = $distro;
        return $distro;
    }

    public function detectDistroByProbing()
    {
        $access = $this->getAccess();
        foreach ($this->distroProbes as $name => $probe) {
            $filename = '/etc/' . $probe['release'];
            $regex = $probe['regex'];
            $content = $access->fileGetContents($filename);

            $found = $content && (
                (isset($regex) && preg_match($regex, $content))
                || $regex === null
            );

            if ($found) {
                return $name;
            }
        }
    }

    // http://0pointer.de/blog/projects/os-release
    public function detectDistroSystemd()
    {
        $access = $this->getAccess();
        $info = $access->fileGetContents('/etc/os-release');
        $info = trim($info);
        $info = parse_ini_string($info);

        if (is_array($info) && isset($info['NAME'])) {
            return $info['NAME'];
        }
    }

    public function detectOS()
    {
        $access = $this->getAccess();
        $command = $access->createCommand('php', ['-r', 'echo PHP_OS;']);
        $command->run();

        $out = null;
        if ($command->getReturn() === 0) {
            $out = $command->getStdoutContent();
            $out = trim($out);
            $out = strtoupper($out);
            $this->config['os'] = $out;
            return $out;
        }

        $out = $command->getStderrContent();
        $out = trim($out);

        throw new ConfigException(
            sprintf("Failed to detect OS: %s", $out),
            ConfigException::DETECT_ERROR
        );
    }

    public function detectPHP($sel = 0)
    {
        $os = $this->getConf('os') ?: $this->detectOS();
        $distro = $this->getConf('distro') ?: $this->detectDistro();
        $sel = !is_numeric($sel) ? null : intval($sel, 10);

        if ($os === 'WINDOWS' || $os === 'WINNT') {
            $result = $this->detectPHPWindows();
        } elseif ($distro === 'ClearOS') {
            $result = $this->detectPHPClearOS();
        } else {
            $result = $this->detectPHPLinux();
        }

        if ($sel === null) {
            return $result;
        } elseif (empty($result) || !isset($result[$sel])) {
            return null;
        }
        $this->config['phpexec'] = $result[$sel];
        return $this->config['phpexec'];
    }

    public function detectPHPLinux($options = null, $searchOrder = null)
    {
        if ($searchOrder === null) {
            $searchOrder = [
                ['command', ['-v', 'php']],
                ['locate', ['-r', 'bin/php$']],
            ];
        }

        $out = null;
        foreach ($searchOrder as $commandSearch) {
            $access = $this->getAccess();
            $command = $access->createCommand($commandSearch[0], $commandSearch[1]);
            if (!empty($options) && is_array($options)) {
                foreach ($options as $o => $v) {
                    $command->setOption($o, $v);
                }
            }
            $command->run();

            $result = [];
            if ($command->getReturn() === 0) {
                $out = $command->getStdout();
                $line = fgets($out);

                while ($line !== false) {
                    $result[] = trim($line);
                    $line = fgets($out);
                }
                return $result;
            }
        }

        throw new ConfigException(
            sprintf("Failed to detect PHP: %s", $out),
            ConfigException::DETECT_ERROR
        );
    }

    public function detectPHPClearOS()
    {
        $webroot = $this->getConf('webroot') ?: $this->detectWebroot();
        $access = $this->getAccess();

        $command = $access->createCommand('test', ['-d', $webroot]);
        $command->run();

        if ($command->getReturn() === 0) {
            $options = ['cwd' => $webroot];
        } else {
            $options = null;
        }

        $searchOrder = [
            ['command', ['-v', '/usr/clearos/bin/php']], // preference to use the php wrapper
            ['command', ['-v', 'php']],
            ['locate', ['-r', 'bin/php$']],
        ];

        return $this->detectPHPLinux($options, $searchOrder);
    }

    public function detectPHPWindows()
    {
        $access = $this->getAccess();
        $command = $access->createCommand('where', [
            '$path:php.exe',
            '$path:php5.exe',
            '$path:php7.exe',
        ]);
        $command->run();

        $result = [];
        $out = null;
        if ($command->getReturn() === 0) {
            $out = $command->getStdout();
            $line = fgets($out);

            while ($line !== false) {
                $result[] = trim($line);
                $line = fgets($out);
            }
            return $result;
        }
        throw new ConfigException(
            sprintf("Failed to detect PHP: %s", $out),
            ConfigException::DETECT_ERROR
        );
    }

    public function detectPHPVersion()
    {
        $access = $this->getAccess();
        $phpexec = $this->getConf('phpexec') ?: $this->detectPHP();
        $command = $access->createCommand($phpexec, ['-r', 'echo PHP_VERSION_ID;']);
        $command->run();
        $out = null;
        if ($command->getReturn() === 0) {
            $version = trim($command->getStdoutContent());
            $version = intval($version, 10);
            return $version;
        }
        throw new ConfigException(
            sprintf("Failed to detect PHP Version: %s", $out),
            ConfigException::DETECT_ERROR
        );
    }

    public function detectUser()
    {
        $os = $this->getConf('os') ?: $this->detectOS();
        if ($os === 'LINUX') {
            $user = $this->detectUserLinux();
        } else {
            $user = $this->detectUserPHP();
        }
        $this->config['user'] = $user;
        return $user;
    }

    public function detectUserLinux()
    {
        $access = $this->getAccess();
        $command = $access->createCommand('id', ['-un']);
        $command->run();

        $out = null;
        if ($command->getReturn() === 0) {
            $out = $command->getStdoutContent();
            $out = trim($out);
            $this->config['user'] = $out;
            return $out;
        }

        throw new ConfigException(
            sprintf("Failed to detect User: %s", $out),
            ConfigException::DETECT_ERROR
        );
    }

    public function detectUserPHP()
    {
        $access = $this->getAccess();
        $script = '<?php echo '
            . 'function_exists("posix_getpwuid")'
            . '? posix_getpwuid(posix_geteuid())["name"]'
            . ': ('
            .     'isset($_SERVER, $_SERVER["USER"])'
            .     '? $_SERVER["USER"]'
            .     ': ""'
            . ');';

        $command = $access->createCommand('php', [], $script);
        $command->run();

        $out = null;
        if ($command->getReturn() === 0) {
            $out = $command->getStdoutContent();
            $out = trim($out);
            $this->config['user'] = $out;
            return $out;
        }

        throw new ConfigException(
            sprintf("Failed to detect User: %s", $out),
            ConfigException::DETECT_ERROR
        );
    }

    public function detectVcsType()
    {
        $instance = $this->instance;
        $access = $this->getAccess();
        $webroot = rtrim($instance->webroot, DIRECTORY_SEPARATOR);

        if ($access->fileExists($webroot . DIRECTORY_SEPARATOR . '.svn')) {
            return 'SVN';
        }

        if ($access->fileExists($webroot . DIRECTORY_SEPARATOR . '.git')) {
            return 'GIT';
        }

        if ($access->fileExists($webroot . DIRECTORY_SEPARATOR . 'tiki-index.php')) {
            return 'TARBALL';
        }

        return null;
    }

    public function detectWebroot()
    {
        $instance = $this->instance;
        $access = $this->getAccess();
        $distro = $this->getConf('distro') ?: $this->detectDistro();
        $user = $this->getConf('user') ?: $this->detectUser();
        $os = $this->getConf('os') ?: $this->detectOS();

        $folder = [
            'base' => '/var/www/html',
            'target' => '/var/www/html/' . $instance->name
        ];

        if ($distro === 'ClearOS') {
            $folder = [
                'base' => '/var/www/virtual',
                'target' => '/var/www/virtual/' . $instance->name . '/html'
            ];
        } elseif ($os === 'WINDOWS' || $os === 'WINNT') {
            $folder = [
                'base' => getenv('systemdrive'),
                'target' => implode(DIRECTORY_SEPARATOR, [getenv('systemdrive') , $instance->name])
            ];
        }

        $canWrite = (
            $access->createCommand('test', [
                    '-d', $folder['target'],
                    '-a',
                    '-w', $folder['target'],
                    '-o',
                    '-d', $folder['base'],
                    '-a',
                    '-w', $folder['base']
                ])->run()->getReturn() === 0
        );

        if ($canWrite) {
            return $folder['target'];
        }

        return sprintf('/home/%s/public_html/%s', $user, $instance->name);
    }

    public function detectWeburl()
    {
        $instance = $this->instance;
        if (!empty($instance->name)) {
            return "https://{$instance->name}";
        }
        if (!empty($access->host)) {
            return "https://{$access->host}";
        }
        return "http://localhost";
    }

    public function detectName()
    {
        $instance = $this->instance;
        if (!empty($instance->weburl)) {
            $url = parse_url($instance->weburl);
            if (isset($url['host'])) {
                return $url['host'];
            }
        }
        if (!empty($access->host)) {
            $name = preg_replace('/[^\w]+/', '-', $access->host);
            return $name;
        }
        return "tikiwiki";
    }

    public function getConf($name)
    {
        if (empty($name)) {
            return $this->config;
        }

        if (isset($this->config[$name])) {
            return $this->config[$name];
        }
    }

    /**
     * @return Access
     */
    public function getAccess()
    {
        if ($this->access) {
            return $this->access;
        }
        $this->access = $this->getInstance()->getBestAccess('scripting');
        return $this->access;
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function setAccess($access)
    {
        $this->access = $access;
    }

    public function setInstance(Instance $instance)
    {
        $this->instance = $instance;
    }
}
