<?php

namespace trim\instance;

class Discovery {
    protected $access;
    protected $instance;
    protected $config = array();

    protected $distroProbes = array(
        "Arch"    => array("release" => "arch-release",    "regex" => null),
        "Ubuntu"  => array("release" => "issue",           "regex" => "/^Ubuntu/"),
        "Debian"  => array("release" => "debian_version",  "regex" => null),
        "Fedora"  => array("release" => "fedora-release",  "regex" => null),
        "ClearOS" => array("release" => "clearos-release", "regex" => null),
        "CentOS"  => array("release" => "centos-release",  "regex" => null),
        "Mageia"  => array("release" => "mageia-release",  "regex" => null),
        "Redhat"  => array("release" => "redhat-release",  "regex" => null)
    );

    public function __construct($instance, $access=null)
    {
        $this->setInstance($instance);
        $this->setAccess($access);
    }

    public function detectBackupPerm()
    {
        $os = $this->getConf('os') ?: $this->detectOS();
        $user = $this->getConf('user') ?: $this->detectUser();
        $distro = $this->getConf('distro') ?: $this->detectDistro();

        if($os === 'WINDOWS') {
            return array('Administrator', 'Administrator', 0750);
        }

        if($distro === 'ClearOS') {
            return array($user, 'allusers', 0750);
        }

        return array($user, $user, 0750);
    }

    public function detectDistro()
    {
        $distro = null;

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
        if(empty($distro) && $found) {
            $distro = $found;
        }

        $this->config['distro'] = $distro;
        return $distro;
    }

    public function detectDistroByProbing()
    {
        $access = $this->getAccess();
        foreach($this->distroProbes as $name => $probe) {
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
        $command = $access->createCommand('php', array('-r', 'echo PHP_OS;'));
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

    public function detectPHP($sel=0)
    {
        $os = $this->getConf('os') ?: $this->detectOS();
        $distro = $this->getConf('distro') ?: $this->detectDistro();
        $sel = !is_numeric($sel) ? null : intval($sel, 10);

        if ($os === 'WINDOWS') {
            $result = $this->detectPHPWindows();
        }
        else if ($distro === 'ClearOS') {
            $result = $this->detectPHPClearOS();
        } else {
            $result = $this->detectPHPLinux();
        }

        if($sel === null) {
            return $result;
        }
        else if(empty($result) || !isset($result[$sel])) {
            return null;
        }
        $this->config['phpexec'] = $result[$sel];
        return $this->config['phpexec'];
    }

    public function detectPHPLinux()
    {
        $access = $this->getAccess();
        $command = $access->createCommand('locate', ['-r', 'bin/php$']);
        $command->run();

        $result = array();
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

    public function detectPHPClearOS()
    {
        $webroot = $this->getConf('webroot') ?: $this->detectWebroot();
        $access = $this->getAccess();

        $command = $access->createCommand('test', array('-d', $webroot));
        $command->run();

        if ($command->getReturn() !== 0) {
            return $this->detectPHPLinux();
        }

        $command = $access->createCommand('php', ['-r', 'echo PHP_BINARY;']);
        $command->setOption('cwd', $webroot);
        $command->run();

        if ($command->getReturn() === 0) {
            $out = $command->getStdout();
            $line = trim( fgets($out) );
            return empty($line) ? array() : array($line);
        }

        throw new ConfigException(
            sprintf("Failed to detect PHP: %s", $out),
            ConfigException::DETECT_ERROR
        );
    }

    public function detectPHPWindows()
    {
        $access = $this->getAccess();
        $command = $access->createCommand('where', array(
            '$path:php.exe',
            '$path:php5.exe',
            '$path:php7.exe',
        ));
        $command->run();

        $result = array();
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

        $command = $access->createCommand('php', array(), $script);
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

    public function detectWebroot()
    {
        $instance = $this->instance;
        $access = $this->getAccess();
        $distro = $this->getConf('distro') ?: $this->detectDistro();
        $user = $this->getConf('user') ?: $this->detectUser();

        $folder = array(
            'base' => '/var/www/html',
            'target' => '/var/www/html/' . $instance->name
        );

        if($distro === 'ClearOS') {
            $folder = array(
                'base' => '/var/www/virtual',
                'target' => '/var/www/virtual/' . $instance->name . '/html'
            );
        }

        $canWrite = (
            $access->createCommand('test', array(
                    '-d', $folder['target'],
                    '-a',
                    '-w', $folder['target'],
                    '-o',
                    '-d', $folder['base'],
                    '-a',
                    '-w', $folder['base']
                ))->run()->getReturn() === 0
        );

        if ($canWrite) {
            return $folder['target'];
        }

        return sprintf('/home/%s/html/%s', $user, $instance->name);
    }

    public function getConf($name)
    {
        if (empty($name)) {
            return $this->config;
        }

        if(isset($this->config[$name])) {
            return $this->config[$name];
        }
    }

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

    public function setInstance($instance)
    {
        $this->instance = $instance;
    }
}

class ConfigException
{
    const DETECT_ERROR = 1;
}
