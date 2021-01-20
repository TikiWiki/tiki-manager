<?php

namespace TikiManager\Tests\Manager\Update;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Config\Environment;
use TikiManager\Manager\Update\Src;
use TikiManager\Manager\UpdateManager;
use TikiManager\Tests\Helpers\Tests;

/**
 * Class SrcUpdateTest
 * @group unit
 */
class SrcTest extends TestCase
{

    /**
     * @var string
     */
    static $testPath;
    /**
     * @var Src
     */
    private $srcUpdate;

    public static function setUpBeforeClass()
    {
        static::$testPath = Environment::get('TEMP_FOLDER') . '/test-update-src';
    }

    protected function setUp()
    {
        $fs = new Filesystem();
        $fs->mkdir(static::$testPath);

        $this->srcUpdate = new Src(static::$testPath);
    }

    protected function tearDown()
    {
        $fs = new Filesystem();
        $fs->remove(static::$testPath);
    }

    public function testUpdate()
    {
        $this->assertFalse(file_exists(static::$testPath . '/tiki-manager'));

        $stub = $this->getMockBuilder(Src::class)->setConstructorArgs([static::$testPath])
            ->setMethods(['runComposerInstall'])->getMock();
        $stub->expects($this->once())
            ->method('runComposerInstall')
            ->will($this->returnValue(null));

        $stub->update();
        $this->assertTrue(file_exists(static::$testPath . '/tiki-manager'));
        $this->assertTrue(file_exists(static::$testPath . DIRECTORY_SEPARATOR . UpdateManager::VERSION_FILENAME));
        $this->assertTrue($stub->hasVersion());
        $this->assertFalse($stub->hasUpdateAvailable(true));
    }

    /**
     * @covers \TikiManager\Manager\Update\Src::hasVersion
     */
    public function testHasVersion()
    {
        $stub = $this->getMockBuilder(Src::class)
            ->setConstructorArgs([static::$testPath])
            ->setMethods(['getCurrentVersion'])
            ->getMock();

        $stub->expects($this->once())->method('getCurrentVersion')
            ->will($this->returnValue('version'));

        $this->assertTrue($stub->hasVersion());
    }

    /**
     * @covers \TikiManager\Manager\Update\Src::hasVersion
     */
    public function testNotHasVersion()
    {
        $stub = $this->getMockBuilder(Src::class)
            ->setConstructorArgs([static::$testPath])
            ->setMethods(['getCurrentVersion'])
            ->getMock();

        $stub->expects($this->once())->method('getCurrentVersion')
            ->will($this->returnValue(false));

        $this->assertFalse($stub->hasVersion());
    }

    public function testGetCurrentVersion()
    {
        $this->assertFalse($this->srcUpdate->getCurrentVersion());

        $version = [
            'version' => md5('version'),
            'date' => date(\DateTime::RFC3339_EXTENDED, strtotime('1year ago'))
        ];

        file_put_contents(static::$testPath . '/' . UpdateManager::VERSION_FILENAME, json_encode($version));

        $this->assertEquals($version, $this->srcUpdate->getCurrentVersion());
    }

    public function testHasUpdateAvailable()
    {

        $file = static::$testPath . '/' . UpdateManager::VERSION_FILENAME;

        $this->assertFalse(file_exists($file));
        $this->assertFalse($this->srcUpdate->hasUpdateAvailable(true));
        $version = $this->getVersion(null, '-1 year');

        file_put_contents($file, json_encode($version));

        $this->assertTrue($this->srcUpdate->hasUpdateAvailable(true));
    }

    public function testNotHasUpdateAvailable()
    {

        $version = $this->getVersion();

        $stub = $this->getMockBuilder(Src::class)
            ->setConstructorArgs([static::$testPath])
            ->setMethods(['getCurrentVersion', 'getRemoteVersion'])
            ->getMock();

        $stub->expects($this->once())->method('getCurrentVersion')
            ->will($this->returnValue($version));
        $stub->expects($this->once())->method('getRemoteVersion')
            ->will($this->returnValue($version));

        $this->assertFalse($stub->hasUpdateAvailable(true));
    }

    public function testRemoteVersionInvalidBranch()
    {
        $this->assertFalse($this->srcUpdate->getRemoteVersion('invalidBranch'));
    }

    public function testGetUpdater()
    {
        $this->assertInstanceOf(Src::class, UpdateManager::getUpdater(static::$testPath));
    }

    /**
     * @covers \TikiManager\Manager\Update\Src::getCurrentVersion
     * @covers \TikiManager\Manager\Update\Src::getType
     * @covers \TikiManager\Manager\Update\Src::info
     */
    public function testGetInfo()
    {
        $version = $this->getVersion();

        $checksumFile = static::$testPath . DIRECTORY_SEPARATOR . UpdateManager::VERSION_FILENAME;
        file_put_contents($checksumFile, json_encode($version));

        $info = $this->srcUpdate->info();

        $this->assertContains('Source Code detected', $info);
        $this->assertContains('Version: ' . $version['version'], $info);
        $this->assertContains('Date: ' . date(\DateTime::COOKIE, strtotime($version['date'])), $info);
    }

    public function testExtractInvalidZip()
    {
        $fs = new Filesystem();
        $file = static::$testPath . DIRECTORY_SEPARATOR . 'test.zip';
        $fs->appendToFile($file, random_bytes(10));

        $result = $this->srcUpdate->extract($file, static::$testPath);

        $this->assertFalse($result);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Failed to retrieve archive file
     */
    public function testUpdateFailDownload()
    {
        $stub = $this->getMockBuilder(Src::class)
            ->setConstructorArgs([static::$testPath])
            ->setMethods(['downloadSrc'])
            ->getMock();

        $stub->expects($this->once())->method('downloadSrc')
            ->will($this->returnValue(false));

        $stub->update();
    }

    private function getVersion($version = null, $date = null)
    {
        return [
            'version' => $version ?? md5('version'),
            'date' => date(\DateTime::RFC3339_EXTENDED, strtotime($date ?? 'now'))
        ];
    }
}
