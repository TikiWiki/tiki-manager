<?php

namespace TikiManager\Tests\Manager;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use TikiManager\Config\Environment;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Manager\Update\Phar;
use TikiManager\Manager\UpdateManager;
use TikiManager\Tests\Helpers\Tests;
use ZipArchive;
use function TikiManager\Tests\Helpers\Tests;

/**
 * @group unit
 */
class PharTest extends TestCase
{

    static $phar;
    static $testPath;

    public static function setUpBeforeClass()
    {
        static::$testPath = Environment::get('TEMP_FOLDER') . '/tests/pharupdate/';
    }

    public function setUp()
    {
        $fs = new Filesystem();
        $fs->mkdir(static::$testPath);
    }

    public function tearDown()
    {
        $fs = new Filesystem();
        $fs->remove(static::$testPath);
        $fs->remove(Environment::get('TEMP_FOLDER') . '/tiki-manager.phar');
    }

    public static function tearDownAfterClass()
    {
        if (static::$phar) {
            $fs = new Filesystem();
            $fs->remove(static::$phar);
        }
    }

    public function testUpdate()
    {
        $currentPhar = static::$testPath . '/tiki-manager.phar';
        $newPhar = static::$testPath . '/tiki-manager-new.phar';

        $fs = new Filesystem();
        $fs->appendToFile($currentPhar, random_bytes(10));
        $fs->appendToFile($newPhar, random_bytes(10));

        $md5 = md5_file($currentPhar);
        $newMd5 = md5_file($newPhar);

        $updater = $this->getMockBuilder(Phar::class)
            ->setConstructorArgs([$currentPhar])
            ->setMethodsExcept(['update'])
            ->getMock();
        $updater->expects($this->any())
            ->method('downloadPhar')
            ->willReturn($newPhar);
        $updater->update();

        $this->assertNotEquals($md5, $newMd5);
        $this->assertTrue(file_exists($currentPhar));

        $this->assertEquals($newMd5, md5_file($currentPhar));
    }

    /**
     * @covers \TikiManager\Manager\Update\Phar::update
     * @expectedException \Exception
     * @expectedExceptionMessage Failed to retrieve phar file
     */
    public function testFailedDownloadWhileUpdating()
    {
        $fs = new Filesystem();
        $currentPhar = static::$testPath . '/tiki-manager.phar';
        $fs->appendToFile($currentPhar, random_bytes(10));

        $updater = $this->getMockBuilder(Phar::class)
            ->setConstructorArgs([$currentPhar])
            ->setMethodsExcept(['update'])
            ->getMock();

        $updater->expects($this->any())
            ->method('downloadPhar')
            ->willReturn('');

        $updater->update();
    }

    /**
     * @covers \TikiManager\Manager\Update\Phar::update
     * @expectedException \Exception
     * @expectedExceptionMessage Failed to update tiki-manager.phar with the new version
     */
    public function testFailReplacePharWhileUpdating()
    {
        $fs = new Filesystem();
        $currentPhar = static::$testPath . '/tiki-manager.phar';
        $fs->appendToFile($currentPhar, random_bytes(10));

        $updater = $this->getMockBuilder(Phar::class)
            ->setConstructorArgs([$currentPhar])
            ->setMethodsExcept(['update'])
            ->getMock();

        $updater->expects($this->any())
            ->method('downloadPhar')
            ->willReturn($currentPhar);

        $updater->update();
    }

    /**
     * @covers \TikiManager\Manager\Update\Phar::getCurrentVersion
     * @covers \TikiManager\Manager\Update\Phar::getType
     * @covers \TikiManager\Manager\Update\Phar::info
     * @throws \Exception
     */
    public function testGetInfo()
    {
        $pharFile = static::buildPhar();
        \Phar::loadPhar($pharFile);
        $updater = new Phar($pharFile);

        $info = $updater->info();

        $expectedInfo = file_get_contents('phar://' . $pharFile . '/' . UpdateManager::VERSION_FILENAME);
        $expectedInfo = json_decode($expectedInfo, true);
        $expectedDate = date(\DateTime::COOKIE, strtotime($expectedInfo['date']));

        $this->assertContains('Phar detected', $info);
        $this->assertContains('Version: ' . $expectedInfo['version'], $info);
        $this->assertContains('Date: ' . $expectedDate, $info);
    }

    /**
     * @covers \TikiManager\Manager\Update\Phar::downloadPhar
     * @expectedException \Exception
     * @expectedExceptionMessage Failed to download file at
     */
    public function testFailDownload()
    {
        $fs = new Filesystem();
        $currentPhar = static::$testPath . '/tiki-manager.phar';
        $fs->appendToFile($currentPhar, random_bytes(10));

        $updated = new Phar($currentPhar, '/invalid/path');
        $updated->downloadPhar();
    }

    /**
     * @covers \TikiManager\Manager\Update\Phar::downloadPhar
     * @covers \TikiManager\Manager\Update\Phar::isValidPhar
     */
    public function testDownloadPharFile()
    {
        $fs = new Filesystem();
        $currentPhar = static::$testPath . '/tiki-manager.phar';
        $fs->touch($currentPhar);

        $pharFile = static::buildPhar();

        $updated = new Phar($currentPhar, $pharFile);
        $filePath = $updated->downloadPhar();

        $this->assertEquals(md5_file($pharFile), md5_file($filePath));
    }

    /**
     * @covers \TikiManager\Manager\Update\Phar::downloadPhar
     * @covers \TikiManager\Manager\Update\Phar::extractZip
     */
    public function testDownloadFromGitlab()
    {
        $fs = new Filesystem();
        $currentPhar = static::$testPath . '/tiki-manager.phar';
        $fs->appendToFile($currentPhar, random_bytes(10));

        $zipPath = Environment::get('TEMP_FOLDER') . '/test.phar.zip';
        $_ENV['UPDATE_PHAR_URL'] = $zipPath;

        // The artifact contains a folder build.
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE)
        {
            $zip->addFile($currentPhar, 'build/tiki-manager.phar');
            $zip->close();
        }

        $this->assertTrue(file_exists($zipPath));

        $updated = new Phar($currentPhar);
        $path = $updated->downloadPhar();

        $expectedPath = Environment::get('TEMP_FOLDER') . '/tiki-manager.phar';
        $this->assertEquals($expectedPath, $path);
    }

    /**
     * @covers \TikiManager\Manager\Update\Phar::downloadPhar
     * @covers \TikiManager\Manager\Update\Phar::isValidPhar
     * @covers \TikiManager\Manager\Update\Phar::extractZip
     * @expectedException \Exception
     * @expectedExceptionMessage Error extracting files
     */
    public function testDownloadFailZipExtraction()
    {
        $fs = new Filesystem();
        $currentPhar = static::$testPath . '/tiki-manager.phar';
        $fs->appendToFile($currentPhar, md5(random_bytes(10)));

        $updated = new Phar($currentPhar, $currentPhar);
        $updated->downloadPhar();
    }

    public static function buildPhar()
    {
        if (!static::$phar) {
            $fs = new Filesystem();
            $rootFolder = Environment::get('TRIM_ROOT');
            $file = $rootFolder . '/build/tiki-manager.phar';
            $build = false;

            if (!file_exists($file)) {
                $build = true;
                $process = new Process(['composer', "--working-dir=$rootFolder", 'build-phar']);
                $process->run();

                if (!$process->isSuccessful()) {
                    return false;
                }
            }

            $targetFile = Environment::get('TEMP_FOLDER') . '/tiki-manager-build.phar';
            $fs->copy($file, $targetFile);
            static::$phar = $targetFile;

            if ($build) {
                $fs->remove(dirname($file));
            }
        }

        return static::$phar;
    }

    /**
     * @covers \TikiManager\Manager\Update\Phar::isValidPhar
     */
    public function testIsValidPhar()
    {
        $validPhar = $this->buildPhar();
        $updater = new Phar($validPhar);
        $result = Tests::invokeMethod($updater, 'isValidPhar', [$validPhar]);
        $this->assertTrue($result);

        $invalidPhar = Environment::get('TEMP_FOLDER') . 'invalidphar.zip';
        $updater = new Phar($invalidPhar);
        $result = Tests::invokeMethod($updater, 'isValidPhar', [$invalidPhar]);
        $this->assertFalse($result);
    }

    public function testGetUpdater()
    {
        $fs = new Filesystem();
        $currentPhar = static::$testPath . '/tiki-manager.phar';
        $fs->appendToFile($currentPhar, random_bytes(10));

        $this->assertInstanceOf(Phar::class, UpdateManager::getUpdater($currentPhar));
    }
}
