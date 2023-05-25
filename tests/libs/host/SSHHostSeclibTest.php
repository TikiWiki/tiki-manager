<?php

namespace TikiManager\Tests\Host;

use PHPUnit\Framework\TestCase;
use TikiManager\Libs\Host\SSH;

/**
 * Class SSH_HostSeclibTest
 * @group unit-ssh
 */
class SSHHostSeclibTest extends SSHHostCommonTest
{
    public function getInstance()
    {
        return new SSH(
            self::$sshHost,
            self::$sshUser,
            self::$sshPort,
            'TikiManager\Libs\Host\SSHSeclibAdapter'
        );
    }
}
