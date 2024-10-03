<?php

namespace TikiManager\Logger;

use Monolog\Handler\AbstractProcessingHandler;

class ArrayHandler extends AbstractProcessingHandler
{
    protected $log = [];

    public function getLog()
    {
        return $this->log;
    }

    protected function write($record): void
    {
        $this->log[] = $record['formatted'];
    }

    public function reset(): void
    {
        $this->log = [];
        parent::reset();
    }
}
