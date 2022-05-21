<?php

namespace TikiManager\Application\Discovery;

use TikiManager\Application\Discovery;
use TikiManager\Application\Exception\ConfigException;

class MacOSDiscovery extends Discovery
{
    protected function detectPHPOS()
    {
        return $this->detectPHPMacOS();
    }

    protected function detectWebrootOS()
    {
        return [
            [
                'base' => '/Applications/MAMP/htdocs',
                'target' => '/Applications/MAMP/htdocs/' . $this->instance->name
            ]
        ];
    }

    protected function detectPHPMacOS($options = [], $searchOrder = null)
    {
        if ($searchOrder === null) {
            $searchOrder = [
                ['command', ['-v', 'php']],
                ['locate', ['-e', '-r', 'bin/php$']],
            ];
        }

        $result = [];
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

            while ($line !== false) {
                $line = trim($line);

                if (!in_array($line, $result)) {
                    $result[] = trim($line);
                }
                $line = fgets($out);
            }
        }

        if (!empty($result)) {
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

    public function detectBackupPerm($path): array
    {
        $user = $group = [];

        if (extension_loaded('posix')) {
            $user = @posix_getpwuid(fileowner($path));
            $group = @posix_getgrgid(filegroup($path));
        }

        $user = $user['name'] ?? $this->detectUser();
        $group= $group['name'] ?? $user;

        $perm = sprintf('%o', fileperms($path) & 0777);

        return [$user, $group, $perm];
    }

    public function isAvailable()
    {
        $os = $this->detectOS();
        return ($os === 'DARWIN');
    }
}
