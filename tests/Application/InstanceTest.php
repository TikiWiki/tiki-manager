<?php

namespace TikiManager\Tests\Application;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Access\Access;
use TikiManager\Access\Local;
use TikiManager\Application\Instance;
use TikiManager\Application\Tiki;
use TikiManager\Application\Version;
use TikiManager\Config\App;
use TikiManager\Config\Environment;
use TikiManager\Libs\Database\Database;
use TikiManager\Libs\Host\Command;
use TikiManager\Style\TikiManagerStyle;

/**
 * Class InstanceTest
 * @package TikiManager\Tests\Application
 * @group unit
 */
class InstanceTest extends TestCase
{
    /** @var Filesystem */
    private $fs;

    private $testDir;

    public static function setUpBeforeClass(): void
    {
        /** @var TikiManagerStyle $io */
        $io = App::get('io');
        $io->setVerbosity(0); // Do not write to console while running tests
    }

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->testDir = Environment::get('TEMP_FOLDER') . '/tests';
        $this->fs->mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->testDir);
    }

    /**
     * @covers \TikiManager\Application\Instance::unlock
     */
    public function testUnlockWithExistingHtaccess()
    {
        $maintenance = $this->testDir . '/maintenance.php';
        $repoHtaccess = $this->testDir . '/_htaccess';
        $htaccess = $this->testDir . '/.htaccess';
        $backupHtaccess = $this->testDir . '/.htaccess.bak';

        $this->fs->touch($maintenance);
        $this->fs->touch($repoHtaccess);
        $this->fs->appendToFile($htaccess, 'in_maintenance');
        $this->fs->appendToFile($backupHtaccess, 'original');

        $result = $this->runUnlock();
        $this->assertTrue($result);

        $this->assertTrue(!$this->fs->exists($maintenance));
        $this->assertTrue(!$this->fs->exists($backupHtaccess));
        $this->assertTrue($this->fs->exists($repoHtaccess));
        $this->assertTrue($this->fs->exists($htaccess));
        $this->assertTrue(!is_link($htaccess), '.htaccess is a symlink');
    }

    /**
     * @covers \TikiManager\Application\Instance::unlock
     */
    public function testUnlockWithHtaccessSymlink()
    {
        $maintenance = $this->testDir . '/maintenance.php';
        $repoHtaccess = $this->testDir . '/_htaccess';
        $htaccess = $this->testDir . '/.htaccess';
        $backupHtaccess = $this->testDir . '/.htaccess.bak';

        $this->fs->touch($maintenance);
        $this->fs->touch($repoHtaccess);
        $this->fs->touch($htaccess);
        $this->fs->symlink($repoHtaccess, $backupHtaccess);

        $result = $this->runUnlock();
        $this->assertTrue($result);

        $this->assertTrue(!$this->fs->exists($maintenance));
        $this->assertTrue(!$this->fs->exists($backupHtaccess));
        $this->assertTrue($this->fs->exists($repoHtaccess));
        $this->assertTrue(is_link($htaccess));
    }

    /**
     * @covers \TikiManager\Application\Instance::configureHtaccess
     * @covers \TikiManager\Application\Instance::unlock
     */
    public function testUnlockShouldCreateHtaccessSymlink()
    {
        $maintenance = $this->testDir . '/maintenance.php';
        $repoHtaccess = $this->testDir . '/_htaccess';
        $htaccess = $this->testDir . '/.htaccess';

        $this->fs->touch($maintenance);
        $this->fs->touch($repoHtaccess);
        $this->fs->touch($htaccess);

        $result = $this->runUnlock();
        $this->assertTrue($result);

        $this->assertTrue(!$this->fs->exists($maintenance));
        $this->assertTrue($this->fs->exists($repoHtaccess));
        $this->assertTrue(is_link($htaccess));
    }

    public function runUnlock()
    {
        $stub = $this->getMockBuilder(Instance::class)
            ->setMethods(['isLocked', 'getBestAccess'])->getMock();
        $stub->webroot = $this->testDir;

        $result = $stub->unlock();
        $this->assertTrue($result);

        $access = new Local($stub);
        $stub->expects($this->atLeastOnce())
            ->method('getBestAccess')
            ->willReturn($access);
        $stub->expects($this->exactly(2))
            ->method('isLocked')
            ->willReturnOnConsecutiveCalls([true, false]);

        return $stub->unlock();
    }

    /**
     * @covers \TikiManager\Application\Instance::unlock
     */
    public function testUnlockOnUnlockedInstance()
    {
        $stub = $this->getMockBuilder(Instance::class)
            ->setMethods(['isLocked'])->getMock();
        $stub->expects($this->once())
            ->method('isLocked')
            ->will($this->returnValue(false));

        $result = $stub->unlock();
        $this->assertTrue($result);
    }

    /**
     * @covers \TikiManager\Application\Instance::installApplication
     */
    public function testInstallApplication()
    {
        $instanceMock = $this->getMockBuilder(Instance::class)
            ->setMethodsExcept(['installApplication'])->getMock();

        $appMock = $this->createMock(Tiki::class);
        $versionMock = $this->createMock(Version::class);

        $appMock
            ->expects($this->once())
            ->method('install')
            ->with($versionMock, false);

        $appMock
            ->expects($this->once())
            ->method('requiresDatabase')
            ->willReturn(true);

        $instanceMock
            ->expects($this->once())
            ->method('database')
            ->willReturn($this->createMock(Database::class));

        $dbConfig = $this->createMock(Database::class);
        $instanceMock
            ->expects($this->once())
            ->method('getDatabaseConfig')
            ->willReturn($dbConfig);

        $appMock
            ->expects($this->once())
            ->method('setupDatabase')
            ->with($dbConfig);

        $appMock
            ->expects($this->never())
            ->method('setPref')
            ->with('tmpDir', $instanceMock->tempdir);

        $instanceMock->installApplication($appMock, $versionMock);
    }

    /**
     * @covers \TikiManager\Application\Instance::reindex
     */
    public function testReindexWithSuccess()
    {
        $tikiMock = $this->createMock(Tiki::class);
        $tikiMock->expects($this->once())
            ->method('getPref')
            ->with('allocate_time_unified_rebuild')
            ->willReturn('600');

        $instanceMock = $this->createPartialMock(Instance::class, ['getBestAccess', 'getApplication']);
        $instanceMock->expects($this->once())
            ->method('getApplication')
            ->willReturn($tikiMock);

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->expects($this->once())
            ->method('getStdoutContent')
            ->willReturn('Rebuilding index done');

        $commandMock
            ->expects($this->once())
            ->method('getReturn')
            ->willReturn('0');

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->expects($this->once())
            ->method('runCommand')
            ->willReturn($commandMock);

        $instanceMock
            ->expects($this->once())
            ->method('getBestAccess')
            ->willReturn($accessMock);

        $this->assertTrue($instanceMock->reindex());
    }

    /**
     * @covers \TikiManager\Application\Instance::reindex
     */
    public function testReindexWithFailure()
    {
        $tikiMock = $this->createMock(Tiki::class);
        $tikiMock->expects($this->once())
            ->method('getPref')
            ->with('allocate_time_unified_rebuild')
            ->willReturn('600');

        $instanceMock = $this->createPartialMock(Instance::class, ['getBestAccess', 'getApplication']);
        $instanceMock->expects($this->once())
            ->method('getApplication')
            ->willReturn($tikiMock);

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->expects($this->once())
            ->method('getStdoutContent')
            ->willReturn('Rebuilding index failed');

        $commandMock
            ->expects($this->never())
            ->method('getReturn')
            ->willReturn('0');

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->expects($this->once())
            ->method('runCommand')
            ->willReturn($commandMock);

        $instanceMock
            ->expects($this->once())
            ->method('getBestAccess')
            ->willReturn($accessMock);

        $this->assertFalse($instanceMock->reindex());
    }
}
