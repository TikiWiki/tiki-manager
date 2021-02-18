<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Application\Discovery;

use PHPUnit\Framework\TestCase;
use TikiManager\Access\Local;
use TikiManager\Application\Discovery\ClearOSDiscovery;
use TikiManager\Application\Instance;
use TikiManager\Libs\Host\Command;

/**
 * Class ClearOSDiscoveryTest
 * @package TikiManager\Tests\Application\Discovery
 * @group unit
 */
class ClearOSDiscoveryTest extends TestCase
{
    /**
     * @covers \TikiManager\Application\Discovery\ClearOSDiscovery::detectWebroot
     * @covers \TikiManager\Application\Discovery\ClearOSDiscovery::detectWebrootOS
     */
    public function testDetectWebroot()
    {
        $mock = $this->createPartialMock(
            ClearOSDiscovery::class,
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

        $this->assertEquals('/var/www/virtual/test.tiki.com/html', $mock->detectWebroot());
        $this->assertEquals('/home/root/public_html/test.tiki.com', $mock->detectWebroot());
    }

    /**
     * @covers \TikiManager\Application\Discovery\ClearOSDiscovery::detectBackupPerm
     */
    public function testDetectBackupPerm()
    {
        $mock = $this->createPartialMock(
            ClearOSDiscovery::class,
            [
                'detectUser',
            ]
        );

        $mock
            ->expects($this->once())
            ->method('detectUser')
            ->willReturn('root');

        $this->assertEquals(['root', 'allusers', 0750], $mock->detectBackupPerm());
    }

    /**
     * @covers \TikiManager\Application\Discovery\ClearOSDiscovery::isAvailable
     */
    public function testIsAvailable()
    {
        $mock = $this->createPartialMock(
            ClearOSDiscovery::class,
            ['detectDistro']
        );

        $mock
            ->expects($this->atMost(2))
            ->method('detectDistro')
            ->willReturnOnConsecutiveCalls('ClearOS', 'LINUX');

        $this->assertTrue($mock->isAvailable());
        $this->assertFalse($mock->isAvailable());
    }

    /**
     * @covers \TikiManager\Application\Discovery\ClearOSDiscovery::detectPHP
     * @covers \TikiManager\Application\Discovery\ClearOSDiscovery::detectPHPOS
     */
    public function testDetectPHP()
    {
        $mock = $this->createPartialMock(
            ClearOSDiscovery::class,
            [
                'detectWebroot',
                'detectPHPLinux'
            ]
        );

        $mock
            ->expects($this->once())
            ->method('detectWebroot')
            ->willReturn('/var/www/virtual/demo.tiki.org/html');

        $mock
            ->expects($this->once())
            ->method('detectPHPLinux')
            ->willReturn(['/usr/clearos/bin/php']);

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getReturn')
            ->willReturn(0);

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->method('createCommand')
            ->willReturn($commandMock);

        $mock->setAccess($accessMock);

        $this->assertEquals('/usr/clearos/bin/php', $mock->detectPHP());
    }
}
