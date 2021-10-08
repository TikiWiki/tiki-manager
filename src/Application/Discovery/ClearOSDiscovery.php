<?php


namespace TikiManager\Application\Discovery;

class ClearOSDiscovery extends LinuxDiscovery
{

    protected function detectPHPOS()
    {
        $webroot = $this->getConf('webroot') ?: $this->detectWebroot();

        $command = $this->access->createCommand('test', ['-d', $webroot]);
        $command->run();

        $options = [];
        if ($command->getReturn() === 0) {
            $options = ['cwd' => $webroot];
        }

        $searchOrder = [
            ['command', ['-v', '/usr/clearos/bin/php']], // preference to use the php wrapper
            ['command', ['-v', 'php']],
            ['locate', ['-e', '-r', 'bin/php$']],
        ];

        return $this->detectPHPLinux($options, $searchOrder);
    }

    protected function detectWebrootOS()
    {
        return [
            [
                'base' => '/var/www/virtual',
                'target' => '/var/www/virtual/' . $this->instance->name . '/html'
            ]
        ];
    }

    public function detectBackupPerm($path): array
    {
        $user = $group = [];

        if (extension_loaded('posix')) {
            $user = @posix_getpwuid(fileowner($path));
            $group = @posix_getgrgid(filegroup($path));
        }

        $user = $user['name'] ?? $this->detectUser();
        $group= $group['name'] ?? 'allusers';

        $perm = sprintf('%o', fileperms($path) & 0777);

        return [$user, $group, $perm];
    }

    public function isAvailable()
    {
        $distro = $this->detectDistro();
        return ($distro === 'ClearOS');
    }
}
