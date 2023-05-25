<?php

namespace TikiManager\Tests\Host;

use TikiManager\Libs\Host\SSH;

/**
 * Class SSH_HostWrapperTest
 * @group unit-ssh
 */
class SSHHostWrapperTest extends SSHHostCommonTest
{
    public function getInstance()
    {
        return new SSH(
            self::$sshHost,
            self::$sshUser,
            self::$sshPort,
            'TikiManager\Libs\Host\SSHWrapperAdapter'
        );
    }
}
