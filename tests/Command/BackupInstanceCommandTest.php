<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Command\BackupInstanceCommand;
use TikiManager\Application\Instance;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use TikiManager\Config\App;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class BackupInstanceCommandTest
 * @group Commands
 * @backupGlobals true
 */
class BackupInstanceCommandTest extends TestCase
{
    protected static $instanceType;
    protected static $instancePath;
    protected static $dbLocalFile;
    protected static $instanceSettings;
    protected static $instanceIds;
    private static $archiveDir;

    public static function setUpBeforeClass(): void
    {
        static::$instanceType = getenv('TEST_INSTANCE_TYPE') ?: 'local';
        $basePath = $_ENV['TESTS_BASE_FOLDER'] . '/backup';

        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'instance']);
        self::$dbLocalFile =  implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'db', 'local.php']);

        self::$archiveDir = rtrim($_ENV['ARCHIVE_FOLDER'], '/');

        $vcs = strtoupper($_ENV['DEFAULT_VCS']);
        $branch = $vcs === 'SRC' ? $_ENV['PREV_SRC_MINOR_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        self::$instanceSettings = [
            'local' => [
                InstanceHelper::WEBROOT_OPTION => self::$instancePath,
                InstanceHelper::BRANCH_OPTION => $branch,
            ],
            'ssh' => [
                InstanceHelper::TYPE_OPTION => 'ssh',
                InstanceHelper::BRANCH_OPTION => $branch,
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

        self::$instanceIds['instance'] = InstanceHelper::create(self::$instanceSettings[static::$instanceType]);
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$instanceIds as $instanceId) {
            $instance = Instance::getInstance($instanceId);
            $access = $instance->getBestAccess();
            $access->shellExec('rm -rf ' . $instance->webroot);
            $instance->delete();
        }

        $fs = new Filesystem();
        $fs->remove($_ENV['TESTS_BASE_FOLDER'] . '/backup');
    }

    public function testBackupInstance()
    {
        // Ensure that instance was created successfully
        $instanceId = self::$instanceIds['instance'];
        $this->assertNotFalse($instanceId);

        $command = new BackupInstanceCommand();

        $arguments = [
            '--instances' => $instanceId,
        ];

        $input = new ArrayInput($arguments, $command->getDefinition());
        $input->setInteractive(false);

        $exitCode = $command->run($input, App::get('output'));

        ob_end_clean();

        $this->assertEquals(0, $exitCode);
    }

    /**
     * @depends testBackupInstance
     */
    public function testCheckBackupFile()
    {
        $archivePath = self::getFullArchivePath();
        $iterator = new FilesystemIterator($archivePath);
        $this->assertCount(1, $iterator);
    }

    /**
     * @depends testCheckBackupFile
     */
    public function testExtractFile()
    {
        $archivePath = self::getFullArchivePath();
        $iterator = new FilesystemIterator($archivePath);
        $backupFile = $iterator->getPathname();

        $command = 'tar -tjf ' . $backupFile . ' > /dev/null';
        $result = shell_exec($command);

        $this->assertEquals(0, $result);
    }

    /**
     * @return string
     */
    private function getFullArchivePath()
    {
        $instance = Instance::getInstance(self::$instanceIds['instance']);
        return self::$archiveDir . DIRECTORY_SEPARATOR . $instance->id . '-' . $instance->name;
    }
}
