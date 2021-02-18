<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Application\Discovery;

use PHPUnit\Framework\TestCase;
use TikiManager\Access\Local;
use TikiManager\Application\Discovery\LinuxDiscovery;
use TikiManager\Application\Exception\ConfigException;
use TikiManager\Application\Instance;
use TikiManager\Libs\Host\Command;

/**
 * Class LinuxDiscoveryTest
 * @package TikiManager\Tests\Application\Discovery
 * @group unit
 */
class LinuxDiscoveryTest extends TestCase
{
    /**
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectWebroot
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectWebrootOS
     */
    public function testDetectWebroot()
    {
        $mock = $this->createPartialMock(
            LinuxDiscovery::class,
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
            ->method('isFolderWriteable')
            ->willReturnOnConsecutiveCalls(true, false, true);

        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->name = 'test.tiki.com';
        $instanceMock->weburl = 'https://test.tiki.com';
        $mock->setInstance($instanceMock);

        $this->assertEquals('/var/www/html/test.tiki.com', $mock->detectWebroot());
        $this->assertEquals('/home/root/public_html/test.tiki.com', $mock->detectWebroot());
    }

    /**
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectBackupPerm
     */
    public function testDetectBackupPerm()
    {
        $mock = $this->createPartialMock(
            LinuxDiscovery::class,
            [
                'detectUser',
            ]
        );

        $mock
            ->expects($this->once())
            ->method('detectUser')
            ->willReturn('apache');

        $this->assertEquals(['apache', 'apache', 0750], $mock->detectBackupPerm());
    }

    /**
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::isAvailable
     */
    public function testIsAvailable()
    {
        $mock = $this->createPartialMock(
            LinuxDiscovery::class,
            ['detectOS']
        );

        $mock
            ->expects($this->atMost(2))
            ->method('detectOS')
            ->willReturnOnConsecutiveCalls('LINUX', 'DARWIN');

        $this->assertTrue($mock->isAvailable());
        $this->assertFalse($mock->isAvailable());
    }

    /**
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectPHP
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectPHPOS
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectPHPLinux
     */
    public function testDetectPHP()
    {
        $mock = $this->createPartialMock(
            LinuxDiscovery::class,
            []
        );

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getReturn')
            ->willReturn(0);

        $stream = fopen('php://memory', 'rw');
        fputs($stream, '/usr/bin/php');
        rewind($stream);

        $commandMock
            ->expects($this->once())
            ->method('getStdout')
            ->willReturn($stream);

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->method('createCommand')
            ->willReturn($commandMock);

        $mock->setAccess($accessMock);

        $this->assertEquals('/usr/bin/php', $mock->detectPHP());
    }

    /**
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectPHP
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectPHPOS
     */
    public function testDetectPHPFails()
    {
        $discoveryMock = $this->createPartialMock(
            LinuxDiscovery::class,
            ['detectPHPOS']
        );

        $discoveryMock
            ->method('detectPHPOS')
            ->willReturn(null);

        $this->assertNull($discoveryMock->detectPHP());
    }

    /**
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectPHP
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectPHPOS
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectPHPLinux
     */
    public function testDetectPHPFailsOnPHPLinux()
    {
        $mock = $this->createPartialMock(
            LinuxDiscovery::class,
            []
        );

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getReturn')
            ->willReturn(1);

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->method('createCommand')
            ->willReturn($commandMock);

        $mock->setAccess($accessMock);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Failed to detect PHP');
        $this->expectExceptionCode(ConfigException::DETECT_ERROR);

        $mock->detectPHP();
    }

    /**
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectUser
     */
    public function testDetectUser()
    {
        $mock = $this->createPartialMock(
            LinuxDiscovery::class,
            []
        );

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getReturn')
            ->willReturn(0);

        $commandMock
            ->method('getStdoutContent')
            ->willReturn('root');

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->expects($this->once())
            ->method('createCommand')
            ->willReturn($commandMock);

        $mock->setAccess($accessMock);

        $this->assertEquals('root', $mock->detectUser());
        // A second time should use the value stored in config
        $this->assertEquals('root', $mock->detectUser());
    }

    /**
     * @covers \TikiManager\Application\Discovery\LinuxDiscovery::detectUser
     */
    public function testDetectUserFails()
    {
        $mock = $this->createPartialMock(
            LinuxDiscovery::class,
            []
        );

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getReturn')
            ->willReturn(1);

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->method('createCommand')
            ->willReturn($commandMock);

        $mock->setAccess($accessMock);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Failed to detect User');
        $this->expectExceptionCode(ConfigException::DETECT_ERROR);

        $mock->detectUser();
    }
}
