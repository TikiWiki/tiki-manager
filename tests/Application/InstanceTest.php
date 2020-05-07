<?php

namespace TikiManager\Tests\Application;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Access\Local;
use TikiManager\Application\Instance;
use TikiManager\Config\App;
use TikiManager\Config\Environment;
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

    public static function setUpBeforeClass()
    {
        /** @var TikiManagerStyle $io */
        $io = App::get('io');
        $io->setVerbosity(0); // Do not write to console while running tests
    }

    public function setUp()
    {
        $this->fs = new Filesystem();
        $this->testDir = Environment::get('TEMP_FOLDER') . '/tests';
        $this->fs->mkdir($this->testDir);
    }

    public function tearDown()
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


}
