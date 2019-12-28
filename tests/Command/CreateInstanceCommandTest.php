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
 * Class CreateInstanceCommandTester
 * @group Commands
 * @backupGlobals true
 */
class CreateInstanceCommandTester extends \PHPUnit\Framework\TestCase
{
    protected static $instancePath;
    protected static $tempPath;
    protected static $instancePathTrunk;
    protected static $dbLocalFileTrunk;
    protected static $instanceSettings = [];

    public static function setUpBeforeClass()
    {
        $basePath = $_ENV['TESTS_BASE_FOLDER'];
        $scriptOwner = get_current_user();

        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'create']);
        self::$tempPath = implode(DIRECTORY_SEPARATOR, [$basePath, 'manager']);
        self::$instancePathTrunk = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'instance']);
        self::$dbLocalFileTrunk = implode(DIRECTORY_SEPARATOR, [self::$instancePathTrunk, 'db', 'local.php']);

        self::$instanceSettings = [
            'local' => [
                InstanceHelper::WEBROOT_OPTION => self::$instancePathTrunk,
                InstanceHelper::TEMPDIR_OPTION => self::$tempPath,
                InstanceHelper::BACKUP_USER_OPTION => isset($scriptOwner) ? $scriptOwner : 'root', // Backup user
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
        $this->assertTrue($fs->exists(self::$instancePathTrunk));
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
}
