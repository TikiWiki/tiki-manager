<?php

namespace TikiManager\Application\Instance;

class CronJob
{
    /** @var bool */
    protected $enabled;

    /** @var string */
    protected $time;

    /** @var string */
    protected $command;

    public function __construct(string $time, string $command, bool $enabled = true)
    {
        $this->time = trim($time);
        $this->command = trim($command);
        $this->enabled = $enabled;
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function setTime(string $time): void
    {
        $this->time = $time;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function format(): string
    {
        $jobLine = sprintf('%s %s', $this->time, $this->command);

        if (!$this->enabled) {
            $jobLine = '#' . $jobLine;
        }

        return $jobLine;
    }

    public function enable()
    {
        $this->enabled = true;
    }

    public function disable()
    {
        $this->enabled = false;
    }
}
