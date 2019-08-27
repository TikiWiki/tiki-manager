<?php
use PHPUnit\Framework\TestCase;
use TikiManager\Application\Restore;

/**
 * Class RestoreTest
 * @group unit
 */
class RestoreTest extends TestCase
{
    public function getBackupPath()
    {
        return $_ENV['TRIM_ROOT'] . '/tests/fixtures/1-tikiwiki_2018-05-31_02-30-50.tar.bz2';
    }

    public function testGetFolderNameFromArchive()
    {
         $archivePath =  $this->getBackupPath();
        $result = Restore::getFolderNameFromArchive($archivePath);
        $this->assertEquals('1-tikiwiki', $result);
    }
}
