<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Application\Discovery;

use PHPUnit\Framework\TestCase;
use TikiManager\Access\Access;
use TikiManager\Access\Local;
use TikiManager\Application\Discovery\VirtualminDiscovery;
use TikiManager\Application\Instance;
use TikiManager\Libs\Host\Command;

/**
 * Class VirtualminDiscoveryTest
 * @package TikiManager\Tests\Application\Discovery
 * @group unit
 */
class VirtualminDiscoveryTest extends TestCase
{
    /**
     * @covers \TikiManager\Application\Discovery\VirtualminDiscovery::detectWebroot
     * @covers \TikiManager\Application\Discovery\VirtualminDiscovery::detectWebrootOS
     */
    public function testDetectWebrootAsRegularUser()
    {
        $mock = $this->createPartialMock(
            VirtualminDiscovery::class,
            [
                'detectUser',
                'isFolderWriteable',
                'getInstance'
            ]
        );
        $mock
            ->method('detectUser')
            ->willReturn('tiki');
        $mock
            ->method('isFolderWriteable')
            ->willReturnOnConsecutiveCalls(true, false, true, false, false, true);

        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->name = 'test.tiki.com';
        $instanceMock->weburl = 'https://test.tiki.com';
        $mock->setInstance($instanceMock);

        $this->assertEquals('/home/tiki/domains/test.tiki.com/public_html', $mock->detectWebroot());
        $this->assertEquals('/home/tiki/public_html', $mock->detectWebroot());
        $this->assertEquals('/var/www/html/test.tiki.com', $mock->detectWebroot());
    }

    /**
     * @covers \TikiManager\Application\Discovery\VirtualminDiscovery::detectWebroot
     * @covers \TikiManager\Application\Discovery\VirtualminDiscovery::detectWebrootOS
     */
    public function testDetectWebrootAsRoot()
    {
        $mock = $this->createPartialMock(
            VirtualminDiscovery::class,
            [
                'detectUser',
                'isFolderWriteable',
                'getInstance'
            ]
        );
        $mock
            ->method('detectUser')
            ->willReturn('root');
        $mock
            ->expects($this->atMost(3))
            ->method('isFolderWriteable')
            ->willReturnOnConsecutiveCalls(false, false, true);

        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->name = 'test.tiki.com';
        $instanceMock->weburl = 'https://test.tiki.com';
        $mock->setInstance($instanceMock);

        $accessMock = $this->createMock(Access::class);
        $accessMock
            ->expects($this->atMost(2))
            ->method('fileExists')
            ->willReturn(false);

        $mock->setAccess($accessMock);

        $this->assertEquals('/var/www/html/test.tiki.com', $mock->detectWebroot());
    }

    /**
     * @covers \TikiManager\Application\Discovery\VirtualminDiscovery::detectWebroot
     * @covers \TikiManager\Application\Discovery\VirtualminDiscovery::detectWebrootOS
     */
    public function testDetectWebrootAsRootMainDomain()
    {
        $mock = $this->createPartialMock(
            VirtualminDiscovery::class,
            [
                'detectUser',
                'isFolderWriteable',
                'getInstance'
            ]
        );
        $mock
            ->method('detectUser')
            ->willReturn('root');
        $mock
            ->expects($this->once())
            ->method('isFolderWriteable')
            ->willReturn(true);

        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->name = 'tiki.com';
        $instanceMock->weburl = 'https://tiki.com';
        $mock->setInstance($instanceMock);

        $accessMock = $this->createMock(Access::class);
        $accessMock
            ->expects($this->atMost(2))
            ->method('fileExists')
            ->willReturn(true);

        $mock->setAccess($accessMock);

        $this->assertEquals('/home/tiki/public_html', $mock->detectWebroot());
    }

    /**
     * @covers \TikiManager\Application\Discovery\VirtualminDiscovery::detectWebroot
     * @covers \TikiManager\Application\Discovery\VirtualminDiscovery::detectWebrootOS
     */
    public function testDetectWebrootAsRootSubdomain()
    {
        $mock = $this->createPartialMock(
            VirtualminDiscovery::class,
            [
                'detectUser',
                'isFolderWriteable',
                'getInstance'
            ]
        );
        $mock
            ->method('detectUser')
            ->willReturn('root');
        $mock
            ->expects($this->once())
            ->method('isFolderWriteable')
            ->willReturn(true);

        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->name = 'test.tiki.com';
        $instanceMock->weburl = 'https://test.tiki.com';
        $mock->setInstance($instanceMock);

        $accessMock = $this->createMock(Access::class);
        $accessMock
            ->expects($this->atMost(5))
            ->method('fileExists')
            ->willReturnOnConsecutiveCalls(false, true, false, true, true);
        $mock->setAccess($accessMock);

        $this->assertEquals('/home/tiki/domains/test.tiki.com/public_html', $mock->detectWebroot());
    }


    /**
     * @covers \TikiManager\Application\Discovery\VirtualminDiscovery::isAvailable
     */
    public function testIsAvailable()
    {
        $mock = $this->createPartialMock(
            VirtualminDiscovery::class,
            []
        );

        $accessMock = $this->createMock(Access::class);
        $accessMock
            ->expects($this->atMost(2))
            ->method('fileExists')
            ->with('/usr/sbin/virtualmin')
            ->willReturnOnConsecutiveCalls(true, false);

        $mock->setAccess($accessMock);

        $this->assertTrue($mock->isAvailable());
        $this->assertFalse($mock->isAvailable());
    }

    /**
     * @covers \TikiManager\Application\Discovery\VirtualminDiscovery::detectDistro
     */
    public function testDetectDistro()
    {
        $mock = $this->createPartialMock(VirtualminDiscovery::class, [
            'detectOS'
        ]);

        $mock
            ->expects($this->once())
            ->method('detectOS')
            ->willReturn('LINUX');


        $accessMock = $this->createMock(Access::class);

        $info = <<<TXT
NAME="Ubuntu"
VERSION="18.04.5 LTS (Bionic Beaver)"
ID=ubuntu
ID_LIKE=debian
PRETTY_NAME="Ubuntu 18.04.5 LTS"
VERSION_ID="18.04"
HOME_URL="https://www.ubuntu.com/"
SUPPORT_URL="https://help.ubuntu.com/"
BUG_REPORT_URL="https://bugs.launchpad.net/ubuntu/"
PRIVACY_POLICY_URL="https://www.ubuntu.com/legal/terms-and-policies/privacy-policy"
VERSION_CODENAME=bionic
UBUNTU_CODENAME=bionic
TXT;

        $accessMock
            ->expects($this->once())
            ->method('fileGetContents')
            ->with('/etc/os-release')
            ->willReturn($info);

        $mock->setAccess($accessMock);

        $this->assertEquals('Ubuntu with Virtualmin', $mock->detectDistro());
    }

    /**
     * @covers \TikiManager\Application\Discovery\ClearOSDiscovery::detectPHP
     * @covers \TikiManager\Application\Discovery\ClearOSDiscovery::detectPHPOS
     */
    public function testDetectPHP()
    {
        $mock = $this->createPartialMock(
            VirtualminDiscovery::class,
            [
                'detectWebroot',
                'detectPHPLinux'
            ]
        );

        $mock
            ->expects($this->once())
            ->method('detectWebroot')
            ->willReturn('/home/tiki/public_html');

        $mock
            ->expects($this->once())
            ->method('detectPHPLinux')
            ->with([], [['command', ['-v', '/home/tiki/bin/php']]])
            ->willReturn(['/home/tiki/bin/php']);

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getReturn')
            ->willReturn(0);
        $commandMock
            ->method('getStdout')
            ->willReturn('/home/tiki/bin/php');

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->method('createCommand')
            ->willReturn($commandMock);

        $accessMock
            ->expects($this->once())
            ->method('fileExists')
            ->with('/home/tiki/public_html/../bin/php')
            ->willReturn(true);

        $mock->setAccess($accessMock);

        $this->assertEquals(['/home/tiki/bin/php'], $mock->detectPHP());
    }
}
