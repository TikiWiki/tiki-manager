<?php

use TikiManager\Libs\Host\SSH;

require_once(__DIR__) . '/SSHHostCommonTest.php';

/**
 * Class SSH_HostWrapperTest
 * @group unit-ssh
 */
class SSH_HostWrapperTest extends SSH_HostCommonTest
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
