<?php

namespace TikiManager\Tests\Libs\VersionControl;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use TikiManager\Access\Local;
use TikiManager\Application\Instance;
use TikiManager\Libs\Host\Command;
use TikiManager\Libs\VersionControl\Git;

class GitTest extends TestCase
{

    /**
     * @expectedException \TikiManager\Application\Exception\VcsException
     */
    public function testConflictsDetectionOnUpdate()
    {
        $instance = $this->createMock(Instance::class);

        $stream = vfsStream::setup('instance');
        $instance->webroot = $stream->url();
        $instance->type = 'local';

        $access = $this->getMockBuilder(Local::class)
            ->setConstructorArgs([$instance])
            ->getMock();

        $command = $this->createMock(Command::class);
        $command->method('getReturn')->willReturn(1);
        $conflictError = <<<TXT
error: Your local changes to the following files would be overwritten by merge:
	lib/core/Search/ContentSource/TrackerFieldSource.php
Please, commit your changes or stash them before you can merge.
Aborting
TXT;

        $command->method('getStderrContent')->willReturn($conflictError);

        $access->method('runCommand')->willReturn($command);
        $instance->expects($this->atLeastOnce())
            ->method('getBestAccess')
            ->willReturn($access);

        $git = $this->getMockBuilder(Git::class)
            ->setConstructorArgs([$instance])
            ->setMethods(['cleanup'])
            ->getMock();

        $git->method('cleanup')->willReturn(true);
        $git->pull($instance->webroot);
    }

    /**
     * @expectedException \TikiManager\Application\Exception\VcsException
     */
    public function testConflictsDetectionOnUpgrade()
    {
        $instance = $this->createMock(Instance::class);

        $stream = vfsStream::setup('instance');
        $instance->webroot = $stream->url();
        $instance->type = 'local';

        $access = $this->getMockBuilder(Local::class)
            ->setConstructorArgs([$instance])
            ->getMock();

        $command = $this->createMock(Command::class);
        $command->method('getReturn')->willReturn(1);
        $conflictError = <<<TXT
error: Your local changes to the following files would be overwritten by checkout:
	lib/core/Search/ContentSource/TrackerFieldSource.php
Please, commit your changes or stash them before you can switch branches.
Aborting
TXT;

        $command->method('getStderrContent')->willReturn($conflictError);

        $access->method('runCommand')->willReturn($command);
        $instance->expects($this->atLeastOnce())
            ->method('getBestAccess')
            ->willReturn($access);

        $git = $this->getMockBuilder(Git::class)
            ->setConstructorArgs([$instance])
            ->setMethods(['revert'])
            ->getMock();

        $git->method('revert')->willReturn(true);
        $git->upgrade($instance->webroot, 'master');
    }

}
