<?php

namespace TikiManager\Hooks;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class HookHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $hooks = [];

    protected $hooksFolder;

    /**
     * @param string $hooksFolder
     * @param LoggerInterface|null $logger
     * @throws \Exception
     */
    public function __construct(string $hooksFolder, ?LoggerInterface $logger = null)
    {
        if (!file_exists($hooksFolder)) {
            throw new \Exception('Hooks folder does not exist.');
        }

        $this->hooksFolder = $hooksFolder;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Get hooks folder
     * @return null|string
     */
    public function getHooksFolder(): string
    {
        return $this->hooksFolder;
    }

    /**
     * Get Command Hook
     *
     * @param string $command
     * @return TikiCommandHook
     */
    public function getHook(string $command): TikiCommandHook
    {
        if (!isset($this->hooks[$command])) {
            $className = 'TikiManager\\Hooks\\' . str_replace([':', '-'], '', ucwords($command, ':')) . 'Hook';

            if (!class_exists($className)) {
                $className = 'TikiManager\\Hooks\\TikiCommandHook';
            }

            $hookName = str_replace(':', '-', str_replace('-', '', $command));
            $this->hooks[$command] = new $className($hookName, $this->logger);
        }

        return $this->hooks[$command];
    }
}
