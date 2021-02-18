<?php

namespace TikiManager\Application\Discovery;

use TikiManager\Application\Discovery;
use TikiManager\Application\Exception\ConfigException;

class LinuxDiscovery extends Discovery
{
    protected function detectPHPOS()
    {
        return $this->detectPHPLinux();
    }

    protected function detectWebrootOS()
    {
        return [
            [
                'base' => '/var/www/html',
                'target' => '/var/www/html/' . $this->instance->name
            ]
        ];
    }

    protected function detectPHPLinux($options = [], $searchOrder = null)
    {
        if ($searchOrder === null) {
            $searchOrder = [
                ['command', ['-v', 'php']],
                ['locate', ['-r', 'bin/php$']],
            ];
        }

        foreach ($searchOrder as $commandSearch) {
            $command = $this->access->createCommand($commandSearch[0], $commandSearch[1]);

            foreach ($options as $o => $v) {
                $command->setOption($o, $v);
            }

            $command->run();

            if ($command->getReturn() !== 0) {
                continue;
            }

            $out = $command->getStdout();
            $line = fgets($out);

            $result = [];
            while ($line !== false) {
                $result[] = trim($line);
                $line = fgets($out);
            }
            return $result;
        }

        throw new ConfigException(
            "Failed to detect PHP",
            ConfigException::DETECT_ERROR
        );
    }

    public function detectUser()
    {
        if (isset($this->config['user'])) {
            return $this->config['user'];
        }

        $command = $this->access->createCommand('id', ['-un']);
        $command->run();

        if ($command->getReturn() === 0) {
            $out = $command->getStdoutContent();
            $out = trim($out);
            $this->config['user'] = $out;
            return $out;
        }

        $out = $command->getStderrContent();
        $out = trim($out);

        throw new ConfigException(
            sprintf('Failed to detect User: %s', $out),
            ConfigException::DETECT_ERROR
        );
    }

    public function detectBackupPerm()
    {
        $user = $this->detectUser();
        return [$user, $user, 0750];
    }

    public function isAvailable()
    {
        $os = $this->detectOS();
        return ($os === 'LINUX');
    }
}
