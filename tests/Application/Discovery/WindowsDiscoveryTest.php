<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Application\Discovery;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use TikiManager\Access\Local;
use TikiManager\Application\Discovery\WindowsDiscovery;
use TikiManager\Application\Exception\ConfigException;
use TikiManager\Application\Instance;
use TikiManager\Libs\Host\Command;

/**
 * Class WindowsDiscoveryTest
 * @package TikiManager\Tests\Application\Discovery
 * @group unit
 */
class WindowsDiscoveryTest extends TestCase
{
    protected static $prevSystemDrive;
    
    public static function setUpBeforeClass()
    {
        static::$prevSystemDrive = getenv('systemdrive');
        putenv('systemdrive=C:');
    }

    public static function tearDownAfterClass()
    {
        putenv('systemdrive=' . static::$prevSystemDrive);
    }

    /**
     * @covers \TikiManager\Application\Discovery\WindowsDiscovery::detectWebroot
     * @covers \TikiManager\Application\Discovery\WindowsDiscovery::detectWebrootOS
     */
    public function testDetectWebroot()
    {
        $mock = $this->createPartialMock(
            WindowsDiscovery::class,
            [
                'detectUser',
                'isFolderWriteable',
                'getInstance'
            ]
        );
        $mock
            ->method('detectUser')
            ->willReturn('Administrator');
        $mock
            ->method('isFolderWriteable')
            ->willReturnOnConsecutiveCalls(true);


        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->name = 'test.tiki.com';
        $instanceMock->weburl = 'https://test.tiki.com';
        $mock->setInstance($instanceMock);

        $expected = 'C:' . DIRECTORY_SEPARATOR . 'test.tiki.com';
        $this->assertEquals($expected, $mock->detectWebroot());
    }

    /**
     * @covers \TikiManager\Application\Discovery\WindowsDiscovery::detectBackupPerm
     */
    public function testDetectBackupPerm()
    {
        $vsfStream = vfsStream::setup('testDir');
        $mock = $this->createPartialMock(
            WindowsDiscovery::class,
            []
        );

        $expectedUser = 'Administrator';
        $expectedGroup = 'Administrator';

        $this->assertEquals(
            [$expectedUser, $expectedGroup, 0750],
            $mock->detectBackupPerm($vsfStream->url())
        );
    }

    /**
     * @covers \TikiManager\Application\Discovery\WindowsDiscovery::isAvailable
     */
    public function testIsAvailable()
    {
        $mock = $this->createPartialMock(
            WindowsDiscovery::class,
            ['detectOS']
        );

        $mock
            ->expects($this->atMost(3))
            ->method('detectOS')
            ->willReturnOnConsecutiveCalls('WINDOWS', 'WINNT', 'LINUX');

        $this->assertTrue($mock->isAvailable());
        $this->assertTrue($mock->isAvailable());
        $this->assertFalse($mock->isAvailable());
    }

    /**
     * @covers \TikiManager\Application\Discovery\WindowsDiscovery::detectPHP
     * @covers \TikiManager\Application\Discovery\WindowsDiscovery::detectPHPOS
     */
    public function testDetectPHP()
    {
        $mock = $this->createPartialMock(
            WindowsDiscovery::class,
            []
        );

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getReturn')
            ->willReturn(0);

        $stream = fopen('php://memory', 'rw');
        fputs($stream, 'C:\\tools\php71\php.exe');
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

        $this->assertEquals('C:\\tools\php71\php.exe', $mock->detectPHP());
    }

    /**
     * @covers \TikiManager\Application\Discovery\WindowsDiscovery::detectPHP
     * @covers \TikiManager\Application\Discovery\WindowsDiscovery::detectPHPOS
     */
    public function testDetectPHPFails()
    {
        $mock = $this->createPartialMock(
            WindowsDiscovery::class,
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
     * @covers \TikiManager\Application\Discovery\WindowsDiscovery::detectUser
     */
    public function testDetectUser()
    {
        $mock = $this->createPartialMock(
            WindowsDiscovery::class,
            []
        );

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getReturn')
            ->willReturn(0);

        $commandMock
            ->method('getStdoutContent')
            ->willReturn('Administrator');

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->expects($this->once())
            ->method('createCommand')
            ->willReturn($commandMock);

        $mock->setAccess($accessMock);

        $this->assertEquals('Administrator', $mock->detectUser());
        // A second time should use the value stored in config
        $this->assertEquals('Administrator', $mock->detectUser());
    }

    /**
     * @covers \TikiManager\Application\Discovery\WindowsDiscovery::detectUser
     */
    public function testDetectUserFails()
    {
        $mock = $this->createPartialMock(
            WindowsDiscovery::class,
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
