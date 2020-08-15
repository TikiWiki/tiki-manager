<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;
use TikiManager\Tests\Helpers\VersionControl;

/**
 * Class CreateInstanceCommandTest
 * @group Commands
 * @backupGlobals true
 */
class CreateInstanceCommandTest extends \PHPUnit\Framework\TestCase
{
    protected static $instancePath;
    protected static $tempPath;
    protected static $dbLocalFileTrunk;
    protected static $instanceSettings = [];
    protected static $dbLocalFile;

    public static function setUpBeforeClass()
    {
        $basePath = $_ENV['TESTS_BASE_FOLDER'] . '/create';

        self::$tempPath = implode(DIRECTORY_SEPARATOR, [$basePath, 'tmp']);
        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'instance']);
        self::$dbLocalFileTrunk = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'db', 'local.php']);
        self::$dbLocalFile = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'db', 'local.php']);

        self::$instanceSettings = [
            'local' => [
                InstanceHelper::WEBROOT_OPTION => self::$instancePath,
                InstanceHelper::TEMPDIR_OPTION => self::$tempPath,
            ]
        ];
    }

    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(self::$instancePath);
    }

    public function testLocalInstance()
    {
        $instanceId = InstanceHelper::create(self::$instanceSettings['local']);

        $fs = new Filesystem();

        $this->assertNotFalse($instanceId);
        $this->assertNotEquals(0, $instanceId);
        $this->assertTrue($fs->exists(self::$instancePath));
        $this->assertTrue($fs->exists(self::$dbLocalFileTrunk));
    }

    /**
     * @depends testLocalInstance
     */
    public function testCreateOnExistingLocalInstance()
    {
        $instanceId = InstanceHelper::create(self::$instanceSettings['local']);
        $this->assertFalse($instanceId);
    }

    public function testLocalInstanceWithoutPrefix()
    {
        $fs = new Filesystem();
        $fs->remove(self::$instancePath);

        $options = array_merge(self::$instanceSettings['local'], ['--db-name' => 'test_db', '--db-prefix' => '']);
        $instanceId = InstanceHelper::create($options);

        $fs = new Filesystem();
        $this->assertNotFalse($instanceId);
        $this->assertNotEquals(0, $instanceId);
        $this->assertTrue($fs->exists(self::$instancePath));
        $this->assertTrue($fs->exists(self::$dbLocalFileTrunk));

        $this->assertTrue(file_exists(self::$dbLocalFile));
        include(self::$dbLocalFile);

        $this->assertEquals($options['--db-name'], $dbs_tiki);
    }
}
