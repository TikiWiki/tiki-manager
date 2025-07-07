<?php

namespace TikiManager\Tests\Application;

use PHPUnit\Framework\TestCase;
use TikiManager\Access\Access;
use TikiManager\Access\Local;
use TikiManager\Application\Discovery;
use TikiManager\Application\Exception\ConfigException;
use TikiManager\Application\Instance;
use TikiManager\Libs\Host\Command;

/**
 * Class DiscoveryTest
 * @package TikiManager\Tests\Application
 * @group unit
 */
class DiscoveryTest extends TestCase
{
    protected $instanceMock;
    protected $accessMock;
    protected $discoveryMock;

    protected function setUp(): void
    {
        $this->getDiscoveryMock();
    }

    /**
     * @covers \TikiManager\Application\Discovery::setInstance
     * @covers \TikiManager\Application\Discovery::getInstance
     */
    public function testSetInstance()
    {
        $instanceMock = $this->createMock(Instance::class);
        $this->discoveryMock->setInstance($instanceMock);

        $this->assertEquals($instanceMock, $this->discoveryMock->getInstance());
    }

    /**
     * @covers \TikiManager\Application\Discovery::setAccess
     * @covers \TikiManager\Application\Discovery::getAccess
     */
    public function testSetAccess()
    {
        $accessMock = $this->createMock(Access::class);
        $this->discoveryMock->setAccess($accessMock);

        $this->assertEquals($accessMock, $this->discoveryMock->getAccess());
    }

    /**
     * @covers \TikiManager\Application\Discovery::detectName
     */
    public function testDetectName()
    {
        $accessMock = $this->createMock(Access::class);
        $instanceMock = $this->createMock(Instance::class);

        $discoveryMock = $this->discoveryMock;
        $discoveryMock->setInstance($instanceMock);
        $discoveryMock->setAccess($accessMock);

        $this->assertEquals('tikiwiki', $discoveryMock->detectName());

        $accessMock->host = 'localhost';
        $this->assertEquals('localhost', $discoveryMock->detectName());

        $instanceMock->weburl = 'https://demo.tiki.test';
        $this->assertEquals('demo.tiki.test', $discoveryMock->detectName());
    }

    /**
     * @covers \TikiManager\Application\Discovery::detectWeburl
     */
    public function testDetectWeburl()
    {
        $accessMock = $this->createMock(Access::class);
        $instanceMock = $this->createMock(Instance::class);

        $discoveryMock = $this->discoveryMock;
        $discoveryMock->setInstance($instanceMock);
        $discoveryMock->setAccess($accessMock);

        $this->assertEquals('http://localhost', $discoveryMock->detectWeburl());

        $accessMock->host = 'server.tiki.test';
        $this->assertEquals('https://server.tiki.test', $discoveryMock->detectWeburl());

        $instanceMock->name = 'demo.tiki.test';
        $this->assertEquals('https://demo.tiki.test', $discoveryMock->detectWeburl());
    }

    /**
     * @covers       \TikiManager\Application\Discovery::detectVcsType
     * @dataProvider getVCSTypeData
     */
    public function testDetectVcsType($expected, $file)
    {
        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->webroot = '/tmp/instance';

        $accessMock = $this->createMock(Access::class);
        $accessMock
            ->method('fileExists')
            ->willReturnOnConsecutiveCalls(
                $file === '.git',
                $file === 'tiki-index.php'
            );

        $discoveryMock = $this->discoveryMock;
        $discoveryMock->setInstance($instanceMock);
        $discoveryMock->setAccess($accessMock);

        $this->assertEquals($expected, $discoveryMock->detectVcsType());
    }

    public function getVCSTypeData()
    {
        return [
            ['GIT', '.git'],
            ['SRC', 'tiki-index.php'],
            [null, 'unable-to-find-vcs']
        ];
    }

    /**
     * @covers \TikiManager\Application\Discovery::detectPHPVersion
     */
    public function testDetectPHPVersion()
    {
        $discoveryMock = $this->discoveryMock;

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getStdoutContent')
            ->willReturn('70414');
        $commandMock
            ->method('getReturn')
            ->willReturn(0);

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->method('createCommand')
            ->willReturn($commandMock);

        $discoveryMock->setAccess($accessMock);

        $this->assertEquals(70414, $discoveryMock->detectPHPVersion());
    }

    /**
     * @covers \TikiManager\Application\Discovery::detectPHPVersion
     */
    public function testDetectPHPVersionFails()
    {
        $discoveryMock = $this->discoveryMock;

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getReturn')
            ->willReturn(1);

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->method('createCommand')
            ->willReturn($commandMock);

        $discoveryMock->setAccess($accessMock);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Failed to detect PHP Version');
        $this->expectExceptionCode(ConfigException::DETECT_ERROR);

        $discoveryMock->detectPHPVersion();
    }

    public function testDetectOS()
    {
        $access = $this->accessMock;

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->expects($this->once())
            ->method('getStdoutContent')
            ->willReturn('Linux');
        $commandMock
            ->expects($this->once())
            ->method('getReturn')
            ->willReturn(0);

        $access
            ->method('createCommand')
            ->willReturn($commandMock);

        $this->assertEquals('LINUX', $this->discoveryMock->detectOS());
        // A second time will use config stored data
        $this->assertEquals('LINUX', $this->discoveryMock->detectOS());
    }

    public function testDetectOSFails()
    {
        $access = $this->accessMock;

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getStderrContent')
            ->willReturn('It failed!');
        $commandMock
            ->method('getReturn')
            ->willReturn(1);

        $access
            ->method('createCommand')
            ->willReturn($commandMock);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Failed to detect OS: It failed!');
        $this->expectExceptionCode(ConfigException::DETECT_ERROR);

        $this->discoveryMock->detectOS();
    }

    /**
     * @covers       \TikiManager\Application\Discovery::detectDistro
     * @covers       \TikiManager\Application\Discovery::detectDistroSystemd
     * @covers       \TikiManager\Application\Discovery::detectDistroByProbing
     * @param $os
     * @param $expectedDistro
     * @dataProvider getDistroTestData
     */
    public function testDetectDistro($os, $expectedDistro)
    {
        $discoveryMock = $this->getDiscoveryMock(['detectOS']);
        $discoveryMock
            ->expects($this->once())
            ->method('detectOS')
            ->willReturn($os);

        if ($expectedDistro == 'Ubuntu') {
            $this->accessMock
                ->method('fileGetContents')
                ->with('/etc/os-release')
                ->willReturn('NAME="Ubuntu"');
        }

        if ($expectedDistro == 'ClearOS') {
            $this->accessMock
                ->expects($this->atLeast(6))
                ->method('fileGetContents')
                ->willReturnOnConsecutiveCalls('', '', '', '', '', 'ClearOS release 7.7.2 (Final)');
        }

        if ($expectedDistro == 'Linux Mint') {
            $this->accessMock
                ->method('fileGetContents')
                ->willReturnOnConsecutiveCalls('NAME="Linux Mint"', '', '', '', '', '', '', '', '');
        }

        $this->assertEquals($expectedDistro, $discoveryMock->detectDistro());
        // A second call can should use stored config
        $this->assertEquals($expectedDistro, $discoveryMock->detectDistro());
    }

    public function getDistroTestData()
    {
        return [
            ['DARWIN', 'OSX'],
            ['WINDOWS', 'Windows'],
            ['LINUX', 'Ubuntu'],
            ['LINUX', 'ClearOS'],
            ['LINUX', 'Linux Mint'],
        ];
    }

    protected function getDiscoveryMock($methods = [])
    {
        $this->instanceMock = $this->createMock(Instance::class);
        $this->accessMock = $this->createMock(Local::class);
        $this->discoveryMock = $this->getMockForAbstractClass(
            Discovery::class,
            [
                $this->instanceMock,
                $this->accessMock
            ],
            '',
            true,
            true,
            true,
            $methods
        );

        return $this->discoveryMock;
    }
}
