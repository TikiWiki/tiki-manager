<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Libs\Helpers\Archive;

/**
 * Class FunctionsTest
 * @group unit
 */
class ArchiveTest extends TestCase
{
    static $createdDirs = [];

    public function tearDown()
    {
        $fs = new Filesystem();
        foreach (static::$createdDirs as $dir) {
            $fs->remove($dir);
        }
    }

    /**
     * @covers \TikiManager\Libs\Helpers\Archive::cleanup
     */
    public function testArchiveCleanUp()
    {
        $fs = new Filesystem();

        $instanceId = 999;
        $instanceName = 'demo.instance';
        $backupDirectory = sprintf('%s/%s-%s/', $_ENV['ARCHIVE_FOLDER'], $instanceId, $instanceName);

        static::$createdDirs[] = $backupDirectory;
        $fs->mkdir($backupDirectory);

        $dateFormat = 'Y-m-d_h-i-s';

        $date1 = (new DateTime('2 days ago'))->format($dateFormat);
        $date2 = (new DateTime('1 days ago'))->format($dateFormat);
        $date3 = (new DateTime())->format($dateFormat);
        $date4 = (new DateTime('8 days ago'))->modify('last saturday')->format($dateFormat);

        $file1 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date1);
        $file2 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date2);
        $file3 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date3);
        $file4 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date4);

        $fs->touch($backupDirectory . $file1);
        $fs->touch($backupDirectory . $file2);
        $fs->touch($backupDirectory . $file3);
        $fs->touch($backupDirectory . $file4);

        $files = glob($backupDirectory . '/*.tar.bz2');
        $this->assertCount(4, $files);

        Archive::cleanup($instanceId, $instanceName);
        $files = glob($backupDirectory . '/*.tar.bz2');
        $this->assertCount(3, $files); // Old backups (more than 7 days should be removed)
        $this->assertFalse($fs->exists($file4));

        Archive::cleanup($instanceId, $instanceName, 2);
        $files = glob($backupDirectory . '/*.tar.bz2');
        $this->assertCount(2, $files);
        $this->assertFalse($fs->exists($file1));
    }

    public function testKeepBackupsOnFirstDayOfMonth()
    {
        $fs = new Filesystem();

        $instanceId = 999;
        $instanceName = 'demo.instance';
        $backupDirectory = sprintf('%s/%s-%s/', $_ENV['ARCHIVE_FOLDER'], $instanceId, $instanceName);

        static::$createdDirs[] = $backupDirectory;
        $fs->mkdir($backupDirectory);

        $dateFormat = 'Y-m-d_h-i-s';

        $date1 = (new DateTime('first day of 1 months ago'))->format($dateFormat);
        $date2 = (new DateTime('first day of this month'))->format($dateFormat);
        $date3 = (new DateTime('first day of last month'))->modify('next saturday')->format($dateFormat);
        $date4 = (new DateTime('1 days ago'))->format($dateFormat);

        $file1 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date1);
        $file2 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date2);
        $file3 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date3);
        $file4 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date4);

        $fs->touch($backupDirectory . $file1);
        $fs->touch($backupDirectory . $file2);
        $fs->touch($backupDirectory . $file3);
        $fs->touch($backupDirectory . $file4);

        $files = glob($backupDirectory . '/*.tar.bz2');
        $this->assertCount(4, $files);

        Archive::cleanup($instanceId, $instanceName);
        $files = glob($backupDirectory . '/*.tar.bz2');
        $this->assertCount(3, $files);
        $this->assertFalse($fs->exists($file3));
    }

    public function testKeepDailyBackups()
    {
        $fs = new Filesystem();

        $instanceId = 999;
        $instanceName = 'demo.instance';
        $backupDirectory = sprintf('%s/%s-%s/', $_ENV['ARCHIVE_FOLDER'], $instanceId, $instanceName);

        static::$createdDirs[] = $backupDirectory;
        $fs->mkdir($backupDirectory);

        $dateFormat = 'Y-m-d_h-i-s';

        $date1 = (new DateTime('4 days ago'))->format($dateFormat);
        $date2 = (new DateTime('3 days ago'))->format($dateFormat);
        $date3 = (new DateTime('2 days ago'))->format($dateFormat);
        $date4 = (new DateTime('1 days ago'))->format($dateFormat);

        $file1 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date1);
        $file2 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date2);
        $file3 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date3);
        $file4 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date4);

        $fs->touch($backupDirectory . $file1);
        $fs->touch($backupDirectory . $file2);
        $fs->touch($backupDirectory . $file3);
        $fs->touch($backupDirectory . $file4);

        $files = glob($backupDirectory . '/*.tar.bz2');
        $this->assertCount(4, $files);

        Archive::cleanup($instanceId, $instanceName);
        $files = glob($backupDirectory . '/*.tar.bz2');
        $this->assertCount(4, $files);
    }

    public function testBackupsOnSundayKeptFor31Days()
    {
        $fs = new Filesystem();

        $instanceId = 999;
        $instanceName = 'demo.instance';
        $backupDirectory = sprintf('%s/%s-%s/', $_ENV['ARCHIVE_FOLDER'], $instanceId, $instanceName);

        static::$createdDirs[] = $backupDirectory;
        $fs->mkdir($backupDirectory);

        $dateFormat = 'Y-m-d_h-i-s';

        $date1 = (new DateTime('first day of last month'))->modify('last sunday')->format($dateFormat);
        $date2 = (new DateTime('1 month ago'))->modify('next sunday')->format($dateFormat);
        $date3 = (new DateTime('last sunday'))->format($dateFormat);
        $date4 = (new DateTime('1 days ago'))->format($dateFormat);

        $file1 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date1);
        $file2 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date2);
        $file3 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date3);
        $file4 = sprintf('%s-%s_%s.tar.bz2', $instanceId, $instanceName, $date4);

        $fs->touch($backupDirectory . $file1);
        $fs->touch($backupDirectory . $file2);
        $fs->touch($backupDirectory . $file3);
        $fs->touch($backupDirectory . $file4);

        $files = glob($backupDirectory . '/*.tar.bz2');
        $this->assertCount(4, $files);

        Archive::cleanup($instanceId, $instanceName);
        $files = glob($backupDirectory . '/*.tar.bz2');
        $this->assertCount(3, $files);
        $this->assertFalse($fs->exists($file1));
    }
}
