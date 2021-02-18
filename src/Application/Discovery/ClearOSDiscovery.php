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
            ['locate', ['-r', 'bin/php$']],
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

    public function detectBackupPerm()
    {
        $user = $this->detectUser();
        return [$user, 'allusers', 0750];
    }

    public function isAvailable()
    {
        $distro = $this->detectDistro();
        return ($distro === 'ClearOS');
    }
}
