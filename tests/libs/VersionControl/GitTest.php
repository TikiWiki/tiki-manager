<?php

namespace TikiManager\Tests\Libs\VersionControl;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Access\Local;
use TikiManager\Application\Exception\VcsException;
use TikiManager\Application\Instance;
use TikiManager\Config\Environment;
use TikiManager\Libs\Host\Command;
use TikiManager\Libs\VersionControl\Git;
use TikiManager\Style\TikiManagerStyle;

/**
 * @group unit
 */
class GitTest extends TestCase
{
    static $workingDir;

    public function setUp()
    {
        $testsPath = Environment::get('TESTS_BASE_FOLDER', '/tmp');
        static::$workingDir = $testsPath . '/'. md5(random_bytes(10));
    }

    public function tearDown()
    {
        $fs = new Filesystem();
        $fs->remove(static::$workingDir);
    }

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

    /**
     * @covers \TikiManager\Libs\VersionControl\Git::update
     * @throws VcsException
     */
    public function testUpdateToDifferentBranch()
    {
        $git = $this->createPartialMock(
            Git::class,
            ['isUpgrade', 'info', 'checkoutBranch', 'cleanup', 'revert', 'remoteSetBranch', 'fetch', 'isShallow']
        );

        $git->expects(self::once())->method('info')->willReturn('22.x');
        $git->expects(self::once())->method('isUpgrade')->willReturn(true);
        $git->expects(self::once())->method('isShallow')->willReturn(true);
        $git->expects($this->once())->method('fetch');
        $git->expects(self::once())->method('remoteSetBranch')->with('/root/tmp', 'master');
        $git->expects(self::once())->method('checkoutBranch')->with('/root/tmp', 'master', null);
        $git->expects(self::once())->method('cleanup');

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->update('/root/tmp', 'master');
    }

    public function testUpdateToDifferentBranchWithLag()
    {
        $git = $this->createPartialMock(
            Git::class,
            ['isUpgrade', 'info', 'checkoutBranch', 'cleanup', 'revert', 'getLastCommit','isShallow', 'fetch', 'remoteSetBranch', 'getVersion']
        );

        $git->expects(self::once())->method('info')->willReturn('22.x');
        $git->expects(self::once())->method('isUpgrade')->willReturn(true);
        $git->expects(self::once())->method('remoteSetBranch')->with('/root/tmp', 'master');
        $git->expects(self::once())->method('isShallow')->willReturn(true);
        $git->expects(self::once())->method('getVersion')->willReturn('2.11.0');
        $git->expects($this->exactly(2))->method('fetch');
        $git->expects(self::once())->method('getLastCommit')->willReturn([
            'commit' => '23e88c718205a34f77ed5a6d1c440ab6681d61d2',
            'date' => 'Sun Nov 29 01:57:25 2020 +0000'
        ]);
        $git->expects(self::once())->method('checkoutBranch')->with(
            '/root/tmp',
            'master',
            '23e88c718205a34f77ed5a6d1c440ab6681d61d2'
        );
        $git->expects(self::once())->method('cleanup');

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->update('/root/tmp', 'master', 1);
    }

    public function testUpdateWithLag()
    {
        $git = $this->createPartialMock(
            Git::class,
            ['isUpgrade', 'info', 'getLastCommit', 'checkoutBranch', 'cleanup', 'isShallow', 'fetch', 'getVersion']
        );

        $git->expects(self::once())->method('info')->willReturn('22.x');
        $git->expects(self::once())->method('isShallow')->willReturn(true);
        $git->expects(self::once())->method('getVersion')->willReturn('2.11.0');
        $git->expects($this->exactly(2))->method('fetch');
        $git->expects(self::once())->method('isUpgrade')->willReturn(false);
        $git->expects(self::once())->method('getLastCommit')->willReturn([
            'commit' => '23e88c718205a34f77ed5a6d1c440ab6681d61d2',
            'date' => 'Sun Nov 29 01:57:25 2020 +0000'
        ]);
        $git->expects(self::once())->method('checkoutBranch')->with(
            '/root/tmp',
            'master',
            '23e88c718205a34f77ed5a6d1c440ab6681d61d2'
        );
        $git->expects(self::once())->method('cleanup');

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->update('/root/tmp', 'master', 1);
    }

    /**
     * @covers \TikiManager\Libs\VersionControl\Git::update
     * @throws VcsException
     */
    public function testUpdateWithStashedChanges()
    {
        $git = $this->createPartialMock(
            Git::class,
            ['isUpgrade', 'info', 'getChangedFiles', 'cleanup', 'stash', 'stashPop', 'fetch', 'isShallow', 'pull']
        );

        $git->setVCSOptions(['allow_stash' => true]);

        $git->expects(self::once())->method('info')->willReturn('22.x');
        $git->expects(self::once())->method('isUpgrade')->willReturn(false);
        $git->expects($this->once())->method('fetch');
        $git->expects($this->once())->method('getChangedFiles')->willReturn(['tiki-index.php']);
        $git->expects($this->once())->method('stash');
        $git->expects(self::once())->method('pull');
        $git->expects($this->once())->method('stashPop');
        $git->expects(self::once())->method('cleanup');

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->update('/root/tmp', '22.x');
    }

    /**
     * @covers \TikiManager\Libs\VersionControl\Git::update
     * @throws VcsException
     */
    public function testUpdateWithChangesStashDisabled()
    {
        $git = $this->createPartialMock(
            Git::class,
            ['isUpgrade', 'info', 'getChangedFiles', 'cleanup', 'stash', 'stashPop', 'fetch', 'isShallow', 'pull']
        );

        $git->expects(self::once())->method('info')->willReturn('22.x');
        $git->expects(self::once())->method('isUpgrade')->willReturn(false);
        $git->expects($this->once())->method('fetch');
        $git->expects($this->never())->method('getChangedFiles');
        $git->expects($this->never())->method('stash');
        $git->expects(self::once())->method('pull');
        $git->expects($this->never())->method('stashPop');
        $git->expects(self::once())->method('cleanup');

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->update('/root/tmp', '22.x');
    }

    /**
     * @covers \TikiManager\Libs\VersionControl\Git::update
     * @throws VcsException
     */
    public function testUpdate()
    {
        $git = $this->createPartialMock(
            Git::class,
            ['isUpgrade', 'info', 'cleanup', 'pull', 'isShallow', 'fetch']
        );

        $git->expects(self::once())->method('info')->willReturn('22.x');
        $git->expects(self::once())->method('isUpgrade')->willReturn(false);
        $git->expects(self::once())->method('isShallow')->willReturn(true);
        // Pull latest changes in case branch was already checked before.
        $git->expects(self::once())->method('fetch');
        $git->expects(self::once())->method('pull');
        $git->expects(self::once())->method('cleanup');

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->update('/root/tmp', 'master');
    }

    /**
     * This covers update/upgrade/upgrade+lag
     * @covers \TikiManager\Libs\VersionControl\Git::update
     * @covers \TikiManager\Libs\VersionControl\Git::upgrade
     */
    public function testUpgrade()
    {
        $path = static::$workingDir;
        $mainBranch = Environment::get('MASTER_BRANCH', 'master');
        $prevVersionBranch = Environment::get('PREV_VERSION_BRANCH', '21.x');

        $git = $this->createPartialMock(Git::class, []);

        $git->setIO($this->createMock(TikiManagerStyle::class));
        $git->setRunLocally(true);

        $repoUrl = Environment::get('GIT_TIKIWIKI_URI');
        $git->setRepositoryUrl($repoUrl);
        $git->clone($prevVersionBranch, $path);

        $branch = $git->getRepositoryBranch($path);
        $this->assertEquals($prevVersionBranch, trim($branch));

        $upgradeBranch = $mainBranch;
        $git->update($path, $upgradeBranch, 10);

        $branch = $git->getRepositoryBranch($path);
        $this->assertEquals($upgradeBranch, trim($branch));

        $revision = $git->getRevision($path);

        $git->update($path, $upgradeBranch);
        $branch = $git->getRepositoryBranch($path);
        $this->assertEquals($upgradeBranch, trim($branch));

        // Check same branch but different revisions;
        $this->assertNotEquals($revision, $git->getRevision($path));
    }
}
