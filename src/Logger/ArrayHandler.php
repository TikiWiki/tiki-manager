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

    protected function write(array $record)
    {
        $this->log[] = $record['formatted'];
    }

    public function reset()
    {
        $this->log = [];
        parent::reset();
    }
}
