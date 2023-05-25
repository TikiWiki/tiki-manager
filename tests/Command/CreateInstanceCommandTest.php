<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Application\Instance;
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

    public static function setUpBeforeClass(): void
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

    public static function tearDownAfterClass(): void
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

        if (static::$instanceType == 'local') {
            $this->assertTrue(is_link(self::$instancePath . '/.htaccess'));
        }

        static::$instanceId = $instanceId;
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
