<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use ReflectionClass;
use PHPUnit\Framework\TestCase;
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
class CreateInstanceCommandTest extends TestCase
{
    protected static $instanceType;
    protected static $instancePath;
    protected static $tempPath;
    protected static $instanceSettings = [];
    protected static $dbLocalFile;
    protected static $instanceId;

    public static function setUpBeforeClass()
    {
        static::$instanceType = getenv('TEST_INSTANCE_TYPE') ?: 'local';
        $basePath = $_ENV['TESTS_BASE_FOLDER'] . '/create';

        self::$tempPath = implode(DIRECTORY_SEPARATOR, [$basePath, 'tmp']);
        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'instance']);
        self::$dbLocalFile = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'db', 'local.php']);

        self::$instanceSettings = [
            'local' => [
                InstanceHelper::WEBROOT_OPTION => self::$instancePath,
            ],
            'ssh' => [
                InstanceHelper::TYPE_OPTION => 'ssh',
                InstanceHelper::HOST_NAME_OPTION => $_ENV['SSH_HOST_NAME'],
                InstanceHelper::HOST_PORT_OPTION => $_ENV['SSH_HOST_PORT'] ?? 22,
                InstanceHelper::HOST_USER_OPTION => $_ENV['SSH_HOST_USER'],
                InstanceHelper::HOST_PASS_OPTION => $_ENV['SSH_HOST_PASS'] ?? null,
                InstanceHelper::WEBROOT_OPTION => self::$instancePath,
                InstanceHelper::DB_HOST_OPTION => $_ENV['SSH_DB_HOST'],
                InstanceHelper::DB_USER_OPTION => $_ENV['SSH_DB_USER'],
                InstanceHelper::DB_PASS_OPTION => $_ENV['SSH_DB_PASS'],
            ]
        ];
    }

    public static function tearDownAfterClass()
    {
        static::deleteInstances();

        $fs = new Filesystem();
        $fs->remove($_ENV['TESTS_BASE_FOLDER'] . '/create');
    }

    public static function deleteInstances()
    {
        if (static::$instanceId && $instance = Instance::getInstance(static::$instanceId)) {
            $access = $instance->getBestAccess();
            $access->shellExec('rm -rf ' . $instance->webroot);
            $instance->delete();
        }

        static::$instanceId = null;
    }

    public function testLocalInstance()
    {
        $instanceId = InstanceHelper::create(self::$instanceSettings[static::$instanceType]);

        $this->assertNotFalse($instanceId);
        $this->assertNotEquals(0, $instanceId);

        $instance = Instance::getInstance($instanceId);
        $access = $instance->getBestAccess();
        $this->assertTrue($access->fileExists(self::$instancePath));
        $this->assertTrue($access->fileExists(self::$dbLocalFile));

        $this->assertEquals(
            $instance->tempdir,
            $instance->getApplication()->getPref('tmpDir'),
        'tmpDir in Tiki does not match with the instance tempdir in TikiManager'
        );

        if (static::$instanceType == 'local') {
            $this->assertTrue(is_link(self::$instancePath . '/.htaccess'));
        }

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
        $options = self::$instanceSettings[static::$instanceType];

        $instanceId = InstanceHelper::create($options);
        $this->assertFalse($instanceId);
    }

    /**
     * @depends testLocalInstance
     */
    public function testCreateWithSameAccessAndWebrootInstance()
    {
        $options = self::$instanceSettings[static::$instanceType];
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

        $options = self::$instanceSettings[static::$instanceType];

        $instanceId = InstanceHelper::create($options);
        $this->assertNotFalse($instanceId);
        $this->assertNotEquals(0, $instanceId);
        $instance = Instance::getInstance($instanceId);
        $access = $instance->getBestAccess();
        $this->assertTrue($access->fileExists(self::$instancePath));
        $this->assertTrue($access->fileExists(self::$dbLocalFile));

        $this->assertEquals(
            $instance->tempdir,
            $instance->getApplication()->getPref('tmpDir'),
            'tmpDir in Tiki does not match with the instance tempdir in TikiManager'
        );

        static::$instanceId = $instanceId;
    }

    public function testLocalInstanceWithoutPrefix()
    {
        static::deleteInstances();

        // A little hack (before removing the `chdir()` in Host\Local.php in chdir method
        if (static::$instanceType == 'local') {
            chdir(dirname(__DIR__, 2));
        }

        $options = array_merge(self::$instanceSettings[static::$instanceType], ['--db-name' => 'test_db', '--db-prefix' => '']);
        $instanceId = InstanceHelper::create($options);

        $this->assertNotFalse($instanceId);
        $this->assertNotEquals(0, $instanceId);
        $instance = Instance::getInstance($instanceId);
        $access = $instance->getBestAccess();
        $this->assertTrue($access->fileExists(self::$instancePath));
        $this->assertTrue($access->fileExists(self::$dbLocalFile));

        $path = $access->downloadFile(self::$dbLocalFile);

        include($path);

        $this->assertEquals($options['--db-name'], $dbs_tiki);

        static::$instanceId = $instanceId;
    }
}
