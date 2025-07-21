<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application\Instance;

use TikiManager\Access\ShellPrompt;
use TikiManager\Application\Instance;

class CrontabManager
{
    protected $instance;

    public function __construct(Instance $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Retrieve a crontab line that matches the provided $command
     *
     * @param string $command
     * @return string
     * @throws \Exception
     */
    protected function getCrontabLine(string $command): string
    {
        $crontab = $this->readCrontab();
        $regex = '/^(.* ' . preg_quote($command, '/') . '.*)$/m';

        preg_match($regex, $crontab, $matches);

        return $matches[1] ?? '';
    }

    /**
     * Read all crontab content
     *
     * @return string
     * @throws \Exception
     */
    public function readCrontab(): string
    {
        $access = $this->instance->getBestAccess();
        if (!$access instanceof ShellPrompt) {
            throw new \Exception('Operation not supported, only Local or SSH access is available');
        }

        $command = $access
            ->createCommand('crontab', ['-l'])
            ->run();
        $output = $command->getStdoutContent();

        if ($command->getReturn() !== 0) {
            $error = $command->getStderrContent();
            /* Special case : the crontab is empty throw bad exit code but access is ok */
            if (!preg_match('/no crontab for .+$/', $error)) {
                throw new \Exception(
                    'Error when trying to read crontab: ' . $error
                );
            }

            $output = '';
        }

        return $output;
    }

    /**
     * Write all crontab content (existing content is replaced)
     *
     * @param string $crontabData
     * @return bool
     * @throws \Exception
     */
    protected function writeCrontab(string $crontabData)
    {
        $access = $this->instance->getBestAccess();
        if (!$access instanceof ShellPrompt) {
            throw new \Exception('Operation not supported, only Local or SSH access is available');
        }

        $crontab = escapeshellarg(trim($crontabData));
        $command = $access
            ->createCommand(sprintf('echo %s | crontab -', $crontab))
            ->run();

        $output = $command->getStdoutContent();
        $exitCode = $command->getReturn();
        if ($exitCode !== 0) {
            throw new \Exception(
                'Error when trying to write crontab: ' . implode(' ', $output)
            );
        }

        return true;
    }

    /**
     * Append a cronjob to the existing crontab list
     *
     * @param CronJob $job
     * @throws \Exception
     */
    public function addJob(CronJob $job)
    {
        $crontab = $this->readCrontab();
        $crontab = trim($crontab);

        $crontab = $crontab . ($crontab ? PHP_EOL : '') . $job->format();

        return $this->writeCrontab($crontab);
    }

    /**
     * Remove a cronjob from the existing crontab
     *
     * @param CronJob $job
     * @return bool
     * @throws \Exception
     */
    public function removeJob(CronJob $job)
    {
        return $this->replaceJob($job, null);
    }

    /**
     * Replace an existing Job from crontab
     *
     * @param CronJob $oldJob
     * @param CronJob|null $newJob If Null, the $oldJob is removed
     * @throws \Exception
     */
    public function replaceJob(CronJob $oldJob, ?CronJob $newJob = null)
    {
        $crontab = $this->readCrontab();
        $crontab = trim($crontab);

        $crontab = str_replace($oldJob->format(), $newJob->format() ?? '', $crontab);

        return $this->writeCrontab($crontab);
    }

    /*********************************
     * Tiki console cronjob functions
     *********************************/

    /**
     * @param string $time
     * @param string $command
     * @param array $options
     * @return CronJob
     */
    public function createConsoleCommandJob(string $time, string $command, array $options = []): CronJob
    {
        $commandLine = $this->prepareConsoleCommand($command, $options) . ' > /dev/null 2>&1';

        return new CronJob($time, $commandLine);
    }

    /**
     * @param string $command
     * @param array $options
     * @return CronJob|null
     * @throws \Exception
     */
    public function getConsoleCommandJob(string $command, array $options = [])
    {
        $commandLine = $this->prepareConsoleCommand($command, $options);
        $crontabLine = $this->getCrontabLine($commandLine);

        if (!$crontabLine) {
            return null;
        }

        // TODO SUPPORT @hourly and other @formats?
        preg_match('/^(#?)((\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+))\W+(.+)$/', $crontabLine, $matches);

        return new CronJob($matches[2], $matches[8], empty($matches[1]));
    }

    protected function prepareConsoleCommand(string $command, array $options = []): string
    {
        $commandLine = sprintf(
            'cd %s && %s console.php %s',
            $this->instance->webroot,
            $this->instance->phpexec,
            $command
        );

        foreach ($options as $option => $value) {
            $commandLine .= sprintf(' --%s=%s', $option, escapeshellarg($value));
        }

        return $commandLine;
    }
}
