<?php

use PHPUnit\Framework\TestCase;
use TikiManager\Libs\Host\SSH;

require_once(__DIR__) . '/SSHHostCommonTest.php';

class SSH_HostWrapperTest extends SSH_HostCommonTest
{
    public function getInstance()
    {
        return new SSH(
            self::TARGET_HOST,
            self::TARGET_USER,
            self::TARGET_PORT,
            'TikiManager\Libs\Host\SSHWrapperAdapter'
        );
    }
}
