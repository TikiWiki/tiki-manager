<?php

namespace TikiManager\Tests\Application;

use PHPUnit\Framework\TestCase;
use TikiManager\Access\Access;
use TikiManager\Access\Local;
use TikiManager\Application\Exception\RestoreErrorException;
use TikiManager\Application\Instance;
use TikiManager\Application\Restore;
use TikiManager\Config\Environment;
use TikiManager\Libs\Host\Command;

/**
 * Class RestoreTest
 * @group unit
 */
class RestoreTest extends TestCase
{
    public function getBackupPath()
    {
        return $_ENV['TRIM_ROOT'] . '/tests/fixtures/1-tikiwiki_2018-05-31_02-30-50.tar.bz2';
    }

    public function testGetFolderNameFromArchive()
    {
        $archivePath = $this->getBackupPath();
        $mock = $this->createPartialMock(Restore::class, []);
        $result = $mock->getFolderNameFromArchive($archivePath);
        $this->assertEquals('1-tikiwiki', $result);
    }

    public function testLock()
    {
        $instance = $this->createMock(Instance::class);
        $instance->type = 'local';
        $instance->name = 'tikiwiki';
        $instance->tempdir = $_ENV['TESTS_BASE_FOLDER'] . DS . md5(random_bytes(10));

        $tempDir = Environment::get('TEMP_FOLDER');
        $tempLock = $tempDir . DS . 'restore.lock';

        $access = $this->createMock(Access::class);
        $access
            ->expects($this->once())
            ->method('fileExists')
            ->with($instance->tempdir . DS . 'restore.lock')
            ->willReturn(false);
        $access
            ->expects($this->once())
            ->method('uploadFile')
            ->with($tempLock, $instance->tempdir . DS . 'restore.lock');

        $instance->method('getBestAccess')->willReturn($access);

        $restore = $this
            ->getMockBuilder(Restore::class)
            ->setConstructorArgs([$instance])
            ->setMethodsExcept(['getAccess', 'lock'])
            ->getMock();

        $restore->lock();
    }

    public function testLockFoundAndActive()
    {
        $instance = $this->createMock(Instance::class);
        $instance->type = 'local';
        $instance->name = 'tikiwiki';
        $instance->tempdir = $_ENV['TESTS_BASE_FOLDER'] . DS . md5(random_bytes(10));

        $access = $this->createMock(Local::class);
        $access
            ->expects($this->once())
            ->method('fileExists')
            ->with($instance->tempdir . DS . 'restore.lock')
            ->willReturn(true);

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getStdoutContent')
            ->willReturn(strtotime('-10min'));

        $access
            ->expects($this->once())
            ->method('createCommand')
            ->willReturn($commandMock);

        $access
            ->expects($this->never())
            ->method('uploadFile');

        $instance->method('getBestAccess')->willReturn($access);

        $restore = $this
            ->getMockBuilder(Restore::class)
            ->setConstructorArgs([$instance])
            ->setMethodsExcept(['getAccess', 'lock'])
            ->getMock();

        $this->expectException(RestoreErrorException::class);
        $this->expectExceptionCode(RestoreErrorException::LOCK_ERROR);

        $restore->lock();
    }

    public function testLockFoundButOld()
    {
        $instance = $this->createMock(Instance::class);
        $instance->type = 'local';
        $instance->name = 'tikiwiki';
        $instance->tempdir = $_ENV['TESTS_BASE_FOLDER'] . DS . md5(random_bytes(10));

        $access = $this->createMock(Local::class);
        $access
            ->expects($this->once())
            ->method('fileExists')
            ->with($instance->tempdir . DS . 'restore.lock')
            ->willReturn(true);

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->method('getStdoutContent')
            ->willReturn(strtotime('-1hour'));

        $access
            ->expects($this->once())
            ->method('createCommand')
            ->willReturn($commandMock);

        $tempDir = Environment::get('TEMP_FOLDER');
        $tempLock = $tempDir . DS . 'restore.lock';

        $access
            ->expects($this->once())
            ->method('uploadFile')
            ->with($tempLock, $instance->tempdir . DS . 'restore.lock');

        $instance->method('getBestAccess')->willReturn($access);

        $restore = $this
            ->getMockBuilder(Restore::class)
            ->setConstructorArgs([$instance])
            ->setMethodsExcept(['getAccess', 'lock'])
            ->getMock();

        $restore->lock();
    }

    public function testUnlock()
    {
        $instance = $this->createMock(Instance::class);
        $instance->type = 'local';
        $instance->name = 'tikiwiki';
        $instance->tempdir = $_ENV['TESTS_BASE_FOLDER'] . DS . md5(random_bytes(10));

        $access = $this->createMock(Access::class);
        $access
            ->expects($this->once())
            ->method('deleteFile')
            ->with($instance->tempdir . DS . 'restore.lock');

        $instance
            ->method('getBestAccess')
            ->willReturn($access);

        $restore = $this
            ->getMockBuilder(Restore::class)
            ->setConstructorArgs([$instance])
            ->setMethodsExcept(['getAccess', 'unlock'])
            ->getMock();

        $restore->unlock();
    }
}
