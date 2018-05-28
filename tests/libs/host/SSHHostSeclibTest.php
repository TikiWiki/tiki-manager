<?php

use PHPUnit\Framework\TestCase;
require_once (__DIR__) . '/SSHHostCommonTest.php';

class SSH_HostSeclibTest extends SSH_HostCommonTest
{
    public function getInstance()
    {
        return new SSH_Host(
            self::TARGET_HOST,
            self::TARGET_USER,
            self::TARGET_PORT,
            'SSH_Host_Seclib_Adapter'
        );
    }
}
