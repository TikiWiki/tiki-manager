<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Access\Local;
use TikiManager\Application\Instance;
use TikiManager\Libs\Host\Command;
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
    protected static $instanceId;

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
        static::deleteInstances();

        $fs = new Filesystem();
        $fs->remove(self::$instancePath);
    }

    public static function deleteInstances() {
        $fs = new Filesystem();

        if (static::$instanceId && $instance = Instance::getInstance(static::$instanceId)) {
            $fs->remove($instance->webroot);
            $instance->delete();
        }

        static::$instanceId = null;
    }

    public function testLocalInstance()
    {
        $instanceId = InstanceHelper::create(self::$instanceSettings['local']);

        $fs = new Filesystem();

        $this->assertNotFalse($instanceId);
        $this->assertNotEquals(0, $instanceId);
        $this->assertTrue($fs->exists(self::$instancePath));
        $this->assertTrue($fs->exists(self::$dbLocalFileTrunk));
        $this->assertTrue(is_link(self::$instancePath . '/.htaccess'));

        static::$instanceId = $instanceId;
    }

    /**
     * @depends testLocalInstance
     */
    public function testReindex()
    {
        $instance = Instance::getInstance(static::$instanceId);
        $this->assertTrue($instance->reindex());

        $reflection = new ReflectionClass($instance);
        $reflection_property = $reflection->getProperty('access');
        $reflection_property->setAccessible(true);
        $stbAccess = $this->createMock(Local::class);
        $stbCommand = $this->createMock(Command::class);
        $stbCommand->method('getReturn')->willReturn(2);
        $stbCommand->method('getStdoutContent')->willReturn('Rebuilding index failed');
        $stbAccess->method('runCommand')->willReturn($stbCommand);
        $reflection_property->setValue($instance, [$stbAccess]);
        $this->assertFalse($instance->reindex());

        $stbCommand = $this->createMock(Command::class);
        $stbCommand->method('getReturn')->willReturn(0);
        $stbCommand->method('getStdoutContent')->willReturn('Rebuilding index done');
        $stbAccess = $this->createMock(Local::class);
        $stbAccess->method('runCommand')->willReturn($stbCommand);
        $reflection_property->setValue($instance, [$stbAccess]);
        $this->assertTrue($instance->reindex());
    }

    /**
     * @depends testLocalInstance
     */
    public function testCreateWithSameNameInstance()
    {
        $options = self::$instanceSettings['local'];

        $instanceId = InstanceHelper::create($options);
        $this->assertFalse($instanceId);
    }

    /**
     * @depends testLocalInstance
     */
    public function testCreateWithSameAccessAndWebrootInstance()
    {
        $options = self::$instanceSettings['local'];
        $options[InstanceHelper::NAME_OPTION] = 'managertest2.tiki.org'; // Instance name needs to be unique

        $instanceId = InstanceHelper::create($options);
        $this->assertFalse($instanceId);
    }

    /**
     * @depends testLocalInstance
     */
    public function testCreateImportInstance()
    {
        // Keep the filesystem
        $instance = Instance::getInstance(static::$instanceId);
        $instance->delete();

        $options = self::$instanceSettings['local'];

        $fs = new Filesystem();
        $instanceId = InstanceHelper::create($options);
        $this->assertNotFalse($instanceId);
        $this->assertNotEquals(0, $instanceId);
        $this->assertTrue($fs->exists(self::$instancePath));
        $this->assertTrue($fs->exists(self::$dbLocalFileTrunk));

        static::$instanceId = $instanceId;
    }

    public function testLocalInstanceWithoutPrefix()
    {
        static::deleteInstances();

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

        static::$instanceId = $instanceId;
    }
}
