<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application;

use TikiManager\Access\Access;
use TikiManager\Application\Discovery\ClearOSDiscovery;
use TikiManager\Application\Discovery\LinuxDiscovery;
use TikiManager\Application\Discovery\VirtualminDiscovery;
use TikiManager\Application\Discovery\WindowsDiscovery;
use TikiManager\Application\Discovery\MacOSDiscovery;
use TikiManager\Application\Exception\ConfigException;
use TikiManager\Config\Environment;

abstract class Discovery
{
    protected $access;
    protected $instance;
    protected $config;

    protected $distroProbes = [
        "Arch"    => ["release" => "arch-release",    "regex" => null],
        "Ubuntu"  => ["release" => "issue",           "regex" => "/^Ubuntu/"],
        "Debian"  => ["release" => "debian_version",  "regex" => null],
        "Fedora"  => ["release" => "fedora-release",  "regex" => null],
        "ClearOS" => ["release" => "clearos-release", "regex" => null],
        "CentOS" => ["release" => "centos-release", "regex" => null],
        "Mageia" => ["release" => "mageia-release", "regex" => null],
        "Redhat" => ["release" => "redhat-release", "regex" => null]
    ];

    public function __construct(Instance $instance, Access $access, $config = [])
    {
        $this->setInstance($instance);
        $this->setAccess($access);
        $this->config = $config;
    }

    abstract public function detectBackupPerm($path):array;

    public function detectOS()
    {
        if (isset($this->config['os'])) {
            return $this->config['os'];
        }
        $command = $this->access->createCommand('php', ['-r', 'echo PHP_OS;']);
        $command->run();

        $out = null;
        if ($command->getReturn() === 0) {
            $out = $command->getStdoutContent();
            $out = trim($out);
            $out = strtoupper($out);

            return $this->config['os'] = $out;
        }

        $out = $command->getStderrContent();
        $out = trim($out);

        throw new ConfigException(
            sprintf("Failed to detect OS: %s", $out),
            ConfigException::DETECT_ERROR
        );
    }

    public function detectDistro()
    {
        if (isset($this->config['distro'])) {
            return $this->config['distro'];
        }
        $os = $this->detectOS();
        $distro = null;
        if ($os == 'DARWIN') {
            $distro = 'OSX';
        }

        if (substr($os, 0, 3) === 'WIN') {
            $distro = 'Windows';
        }

        // attempt 1: check distro on modern Linux (>= 2012)
        if (!$distro && $found = $this->detectDistroSystemd()) {
            $distro = isset($this->distroProbes[$found]) ? $found : null;
        }

        // attempt 2: check by files we know
        if (empty($distro)) {
            $distro = $this->detectDistroByProbing();
        }

        // fallback: when found but not recognized on attempt 1 and failed on attempt 2
        if (empty($distro) && $found) {
            $distro = $found;
        }

        return $this->config['distro'] = $distro;
    }

    private function detectDistroByProbing()
    {
        foreach ($this->distroProbes as $name => $probe) {
            $filename = '/etc/' . $probe['release'];
            $regex = $probe['regex'];
            $content = $this->access->fileGetContents($filename);

            $found = $content && (
                    (isset($regex) && preg_match($regex, $content))
                    || $regex === null
                );

            if ($found) {
                return $name;
            }
        }

        return null;
    }

    // http://0pointer.de/blog/projects/os-release
    private function detectDistroSystemd()
    {
        $info = $this->access->fileGetContents('/etc/os-release');
        $info = trim($info);
        $info = parse_ini_string($info);

        if (is_array($info) && isset($info['NAME'])) {
            return $info['NAME'];
        }
    }

    abstract protected function detectPHPOS();

    public function detectPHP()
    {
        $result = $this->detectPHPOS();

        if (empty($result)) {
            return null;
        }

        return $result;
    }

    public static function createInstance($instance = null, $access = null, $config = [])
    {
        $discover = [
            ClearOSDiscovery::class,
            VirtualminDiscovery::class,
            LinuxDiscovery::class,
            WindowsDiscovery::class,
            MacOSDiscovery::class
        ];

        foreach ($discover as $class) {
            $ins = new $class($instance, $access, $config);
            if ($ins->isAvailable()) {
                break;
            }
        }
        return $ins;
    }

    public function detectPHPVersion($phpexec = null)
    {
        if (!$phpexec) {
            $phpexec = $this->getConf('phpexec') ?: $this->detectPHP();
        }
        $command = $this->access->createCommand($phpexec, ['-r', 'echo PHP_VERSION_ID;']);
        $command->run();

        if ($command->getReturn() === 0) {
            $version = trim($command->getStdoutContent());
            return intval($version, 10);
        }

        $out = $command->getStderrContent();
        $out = trim((string)$out);

        throw new ConfigException(
            sprintf('Failed to detect PHP Version: %s', $out),
            ConfigException::DETECT_ERROR
        );
    }

    abstract public function detectUser();

    abstract public function userExists($user);
    abstract public function groupExists($group);

    abstract protected function detectWebrootOS();

    public function detectVcsType()
    {
        $instance = $this->instance;
        $access = $this->access;
        $webroot = rtrim($instance->webroot, DIRECTORY_SEPARATOR);

        if ($access->fileExists($webroot . DIRECTORY_SEPARATOR . '.git')) {
            return 'GIT';
        }

        if ($access->fileExists($webroot . DIRECTORY_SEPARATOR . 'tiki-index.php')) {
            return 'SRC';
        }

        return null;
    }

    public function detectWebroot(): string
    {
        $folders = $this->detectWebrootOS();

        foreach ($folders as $folder) {
            if ($this->isFolderWriteable($folder)) {
                return $folder['target'];
            }
        }

        $user = $this->getConf('user') ?: $this->detectUser();

        return sprintf('/home/%s/public_html/%s', $user, $this->instance->name);
    }

    public function detectWeburl()
    {
        $instance = $this->instance;
        if (!empty($instance->name)) {
            return "https://{$instance->name}";
        }
        $access = $this->access;
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

        $access = $this->access;
        if (!empty($access->host)) {
            return preg_replace('/[^\w]+/', '-', $access->host);
        }

        return "tikiwiki";
    }

    public function detectTmp(): string
    {
        $folders = $this->detectWebrootOS();

        foreach ($folders as $folder) {
            if (isset($folder['tmp']) && $this->isFolderWriteable($folder)) {
                return $folder['tmp'];
            }
        }

        return sys_get_temp_dir();
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
        return $this->access;
    }

    public function getInstance(): Instance
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

    abstract public function isAvailable();

    protected function isFolderWriteable(array $folder): bool
    {
        $command = $this->access->createCommand('test', [
                '-d', $folder['target'],
                '-a',
                '-w', $folder['target'],
                '-o',
                '-d', $folder['base'],
                '-a',
                '-w', $folder['base']
            ])->run();

        return $command->getReturn() === 0;
    }
}
