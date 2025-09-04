<?php

namespace TikiManager\Tests\Access;

use PHPUnit\Framework\TestCase;
use TikiManager\Access\Local;
use TikiManager\Application\Instance;
use TikiManager\Libs\Host\Command;

/**
 * Class LocalTest
 * @package TikiManager\Tests\Access
 * @group unit
 */
class LocalTest extends TestCase
{
    /**
     * @covers \TikiManager\Access\Local::isEmptyDir
     */
    public function testIsEmptyDir()
    {
        $mockCommand = $this->createMock(Command::class);
        $mockCommand->method('run')->willReturnSelf();
        $mockCommand->method('getStdoutContent')->willReturn('');
        $mockInstance = $this->createMock(Instance::class);

        $stub = $this->getMockBuilder(Local::class)
            ->setConstructorArgs([$mockInstance])
            ->onlyMethods(['createCommand'])
            ->getMock();

        $stub->method('createCommand')->willReturn($mockCommand);

        $this->assertTrue($stub->isEmptyDir('/some/fake/path'));
    }

    /**
     * @covers \TikiManager\Access\Local::isEmptyDir
     */
    public function testIsNotEmptyDir()
    {
        $mockCommand = $this->createMock(Command::class);
        $mockCommand->method('run')->willReturnSelf();
        $mockCommand->method('getStdoutContent')->willReturn('somefile.txt');
        $mockInstance = $this->createMock(Instance::class);

        $stub = $this->getMockBuilder(Local::class)
            ->setConstructorArgs([$mockInstance])
            ->onlyMethods(['createCommand'])
            ->getMock();

        $stub->method('createCommand')->willReturn($mockCommand);

        $this->assertFalse($stub->isEmptyDir('/some/fake/path'));
    }

    /**
     * @covers \TikiManager\Access\Local::createDirectory
     */
    public function testCreateDirectory()
    {
        $mockCommand = $this->createMock(Command::class);
        $mockCommand->method('run')->willReturnSelf();
        $mockCommand->method('getReturn')->willReturn(0);

        $mockInstance = $this->createMock(Instance::class);

        $stub = $this->getMockBuilder(Local::class)
            ->setConstructorArgs([$mockInstance])
            ->onlyMethods(['createCommand'])
            ->getMock();

        $stub->method('createCommand')->willReturn($mockCommand);

        $this->assertTrue($stub->createDirectory('/some/dir/path'));
    }
}
