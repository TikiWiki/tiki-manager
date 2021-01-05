<?php

namespace TikiManager\Tests\Manager\Update;

use Gitonomy\Git\Admin;
use Gitonomy\Git\Repository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Config\Environment;
use TikiManager\Manager\Update\Exception\TrackingInformationNotFoundException;
use TikiManager\Manager\Update\Git;
use TikiManager\Manager\Update\Src;
use TikiManager\Manager\UpdateManager;
use TikiManager\Tests\Helpers\Tests;

/**
 * Class GitTest
 * @group unit
 */
class GitTest extends TestCase
{

    /**
     * @var string
     */
    static $testRepoDir;

    /**
     * @var Git
     */
    static $updater;

    public static function setUpBeforeClass()
    {
        $filesystem = new Filesystem();

        //Clone a new tiki manager instance
        $tmpPath = realpath(Environment::get('TEMP_FOLDER'));
        $testRepoDir = $tmpPath . '/test-update-git';

        if (!file_exists($testRepoDir)) {
            $filesystem->mkdir($testRepoDir);
        }

        Admin::cloneTo($testRepoDir, 'https://gitlab.com/tikiwiki/tiki-manager.git', false);
        $filesystem->mkdir($testRepoDir . '/cache');

        static::$testRepoDir = $testRepoDir;
        static::$updater = new Git($testRepoDir);
    }

    public static function tearDownAfterClass()
    {
        $filesystem = new Filesystem();
        $filesystem->remove(static::$testRepoDir);
    }

    public function testGetBranchName()
    {
        $this->assertTrue(static::$updater->getBranchName() == 'master');
    }

    /**
     * @covers \TikiManager\Manager\Update\Git::hasVersion
     */
    public function testHasVersion()
    {
        $stub = $this->getMockBuilder(Git::class)
            ->setConstructorArgs([static::$testRepoDir])
            ->setMethods(['getCurrentVersion'])
            ->getMock();

        $stub->expects($this->once())->method('getCurrentVersion')
            ->will($this->returnValue('version'));

        $this->assertTrue($stub->hasVersion());
    }

    /**
     * @covers \TikiManager\Manager\Update\Git::hasVersion
     */
    public function testNotHasVersion()
    {
        $stub = $this->getMockBuilder(Git::class)
            ->setConstructorArgs([static::$testRepoDir])
            ->setMethods(['getCurrentVersion'])
            ->getMock();

        $stub->expects($this->once())->method('getCurrentVersion')
            ->will($this->returnValue(false));

        $this->assertFalse($stub->hasVersion());
    }

    /**
     * @covers \TikiManager\Manager\Update\Git::hasUpdateAvailable
     */
    public function testNotHasUpdateAvailable()
    {
        $version = $this->getVersion();

        $stub = $this->getMockBuilder(Git::class)
            ->setConstructorArgs([static::$testRepoDir])
            ->setMethods(['getCurrentVersion', 'getRemoteVersion'])
            ->getMock();
        $stub->expects($this->once())
            ->method('getCurrentVersion')
            ->will($this->returnValue($version));
        $stub->expects($this->once())
            ->method('getRemoteVersion')
            ->will($this->returnValue($version));

        $this->assertFalse($stub->hasUpdateAvailable(true));
    }
    /**
     * @covers \TikiManager\Manager\Update\Git::hasUpdateAvailable
     */
    public function testHasUpdateAvailable()
    {
        $oldVersion = $this->getVersion('1', '-1 month');
        $newVersion = $this->getVersion('2');

        $stub = $this->getMockBuilder(Git::class)
            ->setConstructorArgs([static::$testRepoDir])
            ->setMethods(['getCurrentVersion', 'getRemoteVersion'])
            ->getMock();
        $stub->expects($this->once())
            ->method('getCurrentVersion')
            ->will($this->returnValue($oldVersion));
        $stub->expects($this->once())
            ->method('getRemoteVersion')
            ->will($this->returnValue($newVersion));

        $this->assertTrue($stub->hasUpdateAvailable(true));
    }

    public function testUpdateAvailable()
    {
        $rep = new Repository(static::$testRepoDir);
        $rep->run('reset', ['--hard', 'HEAD^']);

        // This will load with fresh references
        $updater = new Git(static::$testRepoDir);

        $this->assertTrue($updater->hasUpdateAvailable(true));
    }

    public function testUpdate()
    {
        $hash = static::$updater->getCurrentVersion();

        $rep = new Repository(static::$testRepoDir);
        $rep->run('reset', ['--hard', 'HEAD^']);

        $stub = $this->getMockBuilder(Git::class)->setConstructorArgs([static::$testRepoDir])
            ->setMethods(['runComposerInstall'])->getMock();
        $stub->expects($this->once())
            ->method('runComposerInstall')
            ->will($this->returnValue(null));

        $stub->update();
        $this->assertFalse($stub->hasUpdateAvailable(true));

        $updHash = $stub->getCurrentVersion();
        $this->assertEquals($hash, $updHash);
    }

    public function testGetRemoteVersion()
    {
        $hash = static::$updater->getCurrentVersion();
        $remoteHash = Tests::invokeMethod(static::$updater, 'getRemoteVersion', ['origin/master']);

        $this->assertEquals($hash, $remoteHash);
    }

    public function testGetRemoveVersionWithoutTrackingBranch()
    {
        $mock = $this->getMockBuilder(Repository::class)
            ->setConstructorArgs([static::$testRepoDir])
            ->getMock();

        $mock->expects($this->any())
            ->method('run')
            ->with('remote')
            ->will($this->returnValue(''));

        $updater = $this->getMockBuilder(Git::class)->setConstructorArgs([$mock])
            ->setMethodsExcept(['getRemoteVersion'])->getMock();
        $updater->expects($this->any())->method('fetch')->willReturn(null);
        $updater->expects($this->any())->method('getUpstreamBranch')->willThrowException(new TrackingInformationNotFoundException('master'));

        $this->expectException(TrackingInformationNotFoundException::class);
        $updater->getRemoteVersion();
    }

    public function testGetRemoveVersionWithoutMatchingRemoteBranches()
    {
        $result = Tests::invokeMethod(static::$updater, 'getRemoteVersion', ['dummy_master']);
        $this->assertFalse($result);
    }

    /**
     * @covers \TikiManager\Manager\Update\Git::getCurrentVersion
     * @covers \TikiManager\Manager\Update\Git::getType
     * @covers \TikiManager\Manager\Update\Git::info
     */
    public function testGetInfo()
    {
        $version = $this->getVersion();
        $mock = $this->getMockBuilder(Git::class)->setConstructorArgs([static::$testRepoDir])
            ->setMethodsExcept(['info', 'getType'])
            ->getMock();
        $mock->expects($this->any())
            ->method('getBranchName')
            ->willReturn('master');
        $mock->expects($this->any())
            ->method('getCurrentVersion')
            ->willReturn($version);
        $info = $mock->info();

        $this->assertContains('Git detected', $info);
        $this->assertContains('Version: ' . $version['version'], $info);
        $this->assertContains('Date: ' . date(\DateTime::COOKIE, strtotime($version['date'])), $info);
        $this->assertContains('Branch: master', $info);
    }

    public function testGetUpdater()
    {
        $this->assertInstanceOf(Git::class, UpdateManager::getUpdater(static::$testRepoDir));
    }

    private function getVersion($version = null, $date = null)
    {
        return [
            'version' => $version ?? md5('version'),
            'date' => date(\DateTime::RFC3339_EXTENDED, strtotime($date ?? 'now'))
        ];
    }
}
