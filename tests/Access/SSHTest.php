<?php

namespace TikiManager\Tests\Access;

use PHPUnit\Framework\TestCase;
use TikiManager\Access\SSH;
use TikiManager\Libs\Host\Command;

/**
 * Class SSHTest
 * @package TikiManager\Tests\Access
 * @group unit
 */
class SSHTest extends TestCase
{
    /**
     * @covers \TikiManager\Access\SSH::isEmptyDir()
     */
    public function testIsEmptyDir()
    {
        $commandStub = $this->createMock(Command::class);
        $commandStub->method('run')->willReturn($commandStub);
        $commandStub
            ->expects($this->once())
            ->method('getStdoutContent')
            ->willReturn(serialize(['.', '..']));

        $stub = $this->createPartialMock(SSH::class, ['createCommand', 'getInterpreterPath']);


        $stub
            ->expects($this->once())
            ->method('getInterpreterPath')
            ->willReturn('php');

        $stub->method('createCommand')->willReturn($commandStub);

        $output = $stub->isEmptyDir('/tmp');
        $this->assertTrue($output);
    }

    /**
     * @covers \TikiManager\Access\SSH::isEmptyDir()
     */
    public function testIsNotEmptyDir()
    {

        $commandStub = $this->createMock(Command::class);
        $commandStub->method('run')->willReturn($commandStub);
        $commandStub
            ->expects($this->once())
            ->method('getStdoutContent')
            ->willReturn(serialize(['.', '..', 'demo', 'index.php']));

        $stub = $this->createPartialMock(SSH::class, ['createCommand', 'getInterpreterPath']);
        $stub
            ->expects($this->once())
            ->method('getInterpreterPath')
            ->willReturn('php');

        $stub->method('createCommand')->willReturn($commandStub);

        $output = $stub->isEmptyDir('/tmp');

        $this->assertFalse($output);
    }
}
