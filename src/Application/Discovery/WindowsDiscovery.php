<?php


namespace TikiManager\Application\Discovery;

use TikiManager\Application\Discovery;
use TikiManager\Application\Exception\ConfigException;

class WindowsDiscovery extends Discovery
{
    protected function detectPHPOS()
    {
        $command = $this->access->createCommand('where', [
            '$path:php.exe',
            '$path:php5.exe',
            '$path:php7.exe',
        ]);
        $command->run();

        $result = [];

        if ($command->getReturn() === 0) {
            $out = $command->getStdout();
            $line = fgets($out);

            while ($line) {
                $result[] = $line;
                $line = fgets($out);
            }
            return $result;
        }

        throw new ConfigException(
            "Failed to detect PHP",
            ConfigException::DETECT_ERROR
        );
    }

    protected function detectWebrootOS()
    {
        return [
            [
                'base' => getenv('systemdrive'),
                'target' => implode(DIRECTORY_SEPARATOR, [getenv('systemdrive'), $this->instance->name])
            ]
        ];
    }

    public function detectUser()
    {
        if (isset($this->config['user'])) {
            return $this->config['user'];
        }

        $script = '<?php echo '
            . 'function_exists("posix_getpwuid")'
            . '? posix_getpwuid(posix_geteuid())["name"]'
            . ': ('
            . 'isset($_SERVER, $_SERVER["USER"])'
            . '? $_SERVER["USER"]'
            . ': ""'
            . ');';

        $command = $this->access->createCommand('php', [], $script);
        $command->run();

        if ($command->getReturn() === 0) {
            $out = $command->getStdoutContent() ?? '';
            $out = trim($out);
            $this->config['user'] = $out;
            return $out;
        }

        $out = $command->getStderrContent() ?? '';
        $out = trim($out);

        throw new ConfigException(
            sprintf('Failed to detect User: %s', $out),
            ConfigException::DETECT_ERROR
        );
    }

    public function detectBackupPerm($path): array
    {
        return ['Administrator', 'Administrator', 0750];
    }

    public function isAvailable()
    {
        $os = $this->detectOS();
        return ($os === 'WINDOWS' || $os === 'WINNT');
    }
}
