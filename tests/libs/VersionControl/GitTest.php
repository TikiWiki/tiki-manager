<?php

namespace TikiManager\Tests\Libs\VersionControl;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use TikiManager\Access\Local;
use TikiManager\Application\Exception\VcsException;
use TikiManager\Application\Instance;
use TikiManager\Libs\Host\Command;
use TikiManager\Libs\VersionControl\Git;
use TikiManager\Style\TikiManagerStyle;

/**
 * @group unit
 */
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

    public function testGetLastCommit()
    {
        $git = $this->createPartialMock(Git::class, ['log']);

        $gitLogOutput = <<<TXT
commit 23e88c718205a34f77ed5a6d1c440ab6681d61d2
Merge: a816cc5 305fe25
Author: John Doe <john.doe@example.com>
Date:   Sun Nov 29 01:57:25 2020 +0000

    Merge branch '22.x' into master 
TXT;

        $timestamp = strtotime('10 days ago');
        $lagDate = date('Y-m-d H:i', $timestamp);

        $git->expects(self::once())->method('log')->with('/root/tmp', 'origin/master',
            ['-1', '--before=' . escapeshellarg($lagDate)])->willReturn($gitLogOutput);
        $lastCommit = $git->getLastCommit('/root/tmp', 'master', $timestamp);

        $this->assertEquals('23e88c718205a34f77ed5a6d1c440ab6681d61d2', $lastCommit['commit']);
        $this->assertEquals('Sun Nov 29 01:57:25 2020 +0000', $lastCommit['date']);
    }

    public function testGetLastCommitInvalidOutput()
    {
        $git = $this->createPartialMock(Git::class, ['log']);

        $this->expectException(VcsException::class);
        $this->expectExceptionMessage('Git log returned with empty output');

        $git->expects(self::once())->method('log')->willReturn('');
        $git->getLastCommit('/root/tmp', 'master');
    }

    public function testGetLastCommitFailParseOutput()
    {
        $git = $this->createPartialMock(Git::class, ['log']);

        $this->expectException(VcsException::class);
        $this->expectExceptionMessage('Unable to parse Git log output');

        $git->expects(self::once())->method('log')->willReturn('invalid output');
        $git->getLastCommit('/root/tmp', 'master');
    }

    public function testUpdateToDifferentBranch()
    {
        $git = $this->createPartialMock(Git::class, ['isUpgrade', 'info', 'upgrade', 'cleanup', 'revert', 'pull']);

        $git->expects(self::once())->method('info')->willReturn('22.x');
        $git->expects(self::once())->method('isUpgrade')->willReturn(true);
        $git->expects(self::once())->method('revert');
        $git->expects(self::once())->method('upgrade')->with('/root/tmp', 'master', null);
        $git->expects(self::once())->method('cleanup');
        // Pull latest changes in case branch was already checked before.
        $git->expects(self::once())->method('pull');

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->update('/root/tmp', 'master');
    }

    public function testUpdateToDifferentBranchWithLag()
    {
        $git = $this->createPartialMock(Git::class,
            ['isUpgrade', 'info', 'upgrade', 'cleanup', 'revert', 'getLastCommit']);

        $git->expects(self::once())->method('info')->willReturn('22.x');
        $git->expects(self::once())->method('isUpgrade')->willReturn(true);
        $git->expects(self::once())->method('revert');
        $git->expects(self::once())->method('getLastCommit')->willReturn(['commit' => '23e88c718205a34f77ed5a6d1c440ab6681d61d2',
            'date' => 'Sun Nov 29 01:57:25 2020 +0000'
        ]);
        $git->expects(self::once())->method('upgrade')->with('/root/tmp', 'master',
            '23e88c718205a34f77ed5a6d1c440ab6681d61d2');
        $git->expects(self::once())->method('cleanup');

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->update('/root/tmp', 'master', 1);
    }

    public function testUpdateWithLag()
    {
        $git = $this->createPartialMock(Git::class,
            ['isUpgrade', 'info', 'getLastCommit', 'checkoutBranch', 'cleanup']);

        $git->expects(self::once())->method('info')->willReturn('22.x');
        $git->expects(self::once())->method('isUpgrade')->willReturn(false);
        $git->expects(self::once())->method('getLastCommit')->willReturn(['commit' => '23e88c718205a34f77ed5a6d1c440ab6681d61d2',
            'date' => 'Sun Nov 29 01:57:25 2020 +0000'
        ]);
        $git->expects(self::once())->method('checkoutBranch')->with('/root/tmp', 'master',
            '23e88c718205a34f77ed5a6d1c440ab6681d61d2');
        $git->expects(self::once())->method('cleanup');

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->update('/root/tmp', 'master', 1);
    }

    public function testUpdate()
    {
        $git = $this->createPartialMock(Git::class, ['isUpgrade', 'info', 'cleanup', 'pull']);

        $git->expects(self::once())->method('info')->willReturn('22.x');
        $git->expects(self::once())->method('isUpgrade')->willReturn(false);
        // Pull latest changes in case branch was already checked before.
        $git->expects(self::once())->method('pull');
        $git->expects(self::once())->method('cleanup');

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->update('/root/tmp', 'master');
    }
}
