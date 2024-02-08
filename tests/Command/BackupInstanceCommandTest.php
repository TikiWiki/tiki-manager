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
use TikiManager\Command\TikiManagerCommand;
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
    private static $backupDir;
    private static $archiveDir;

    public static function setUpBeforeClass(): void
    {
        static::$instanceType = getenv('TEST_INSTANCE_TYPE') ?: 'local';
        $basePath = $_ENV['TESTS_BASE_FOLDER'] . '/backup';

        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'instance']);
        self::$dbLocalFile =  implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'db', 'local.php']);

        self::$backupDir = rtrim($_ENV['BACKUP_FOLDER'], '/');
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
        $instance = Instance::getInstance(self::$instanceIds['instance']);
        $instanceId = $instance->id;
        $instanceName = $instance->name;

        $this->assertNotFalse($instanceId);

        $backupCommand = new BackupInstanceCommand();

        $arguments = [
            '--instances' => $instanceId,
        ];

        $input = new ArrayInput($arguments, $backupCommand->getDefinition());
        $input->setInteractive(false);

        $exitCode = $backupCommand->run($input, App::get('output'));

        $this->assertEquals(0, $exitCode);
        $this->checkHookVars($backupCommand);

        // Check if the .sql database dump file is indeed created during instance backup
        $dbDumpPath = self::$backupDir . DIRECTORY_SEPARATOR . $instanceId . '-' . $instanceName . DIRECTORY_SEPARATOR . 'database_dump.sql';
        $this->assertEquals(1, file_exists($dbDumpPath));

        // Check backup file
        $archivePath = self::getFullArchivePath();
        $iterator = new FilesystemIterator($archivePath);
        $this->assertCount(1, $iterator);

        // Test file extraction
        $backupFile = $iterator->getPathname();
        $extractCommand = 'tar -tjf ' . $backupFile . ' > /dev/null';
        $result = shell_exec($extractCommand);
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

    private function checkHookVars(TikiManagerCommand $command)
    {
        // Check HOOK variables
        $hook = $command->getCommandHook();
        $hookVars = $hook->getPostHookVars();

        $expectedVariables = [
            'INSTANCE_TYPE_',
            'INSTANCE_VCS_TYPE_',
            'INSTANCE_NAME_',
            'INSTANCE_WEBROOT_',
            'INSTANCE_WEBURL_',
            'INSTANCE_TEMPDIR_',
            'INSTANCE_PHPEXEC_',
            'INSTANCE_PHPVERSION_',
            'INSTANCE_BACKUP_USER_',
            'INSTANCE_BACKUP_GROUP_',
            'INSTANCE_BACKUP_PERM_',
            'INSTANCE_BRANCH_',
            'INSTANCE_LAST_ACTION_',
            'INSTANCE_LAST_ACTION_DATE_',
            'INSTANCE_BACKUP_FILE_'
        ];

        $instances = explode(',', $hookVars['INSTANCE_IDS']);
        foreach ($instances as $instanceId) {
            foreach ($expectedVariables as $expectedVariable) {
                $varName = $expectedVariable . $instanceId;
                $this->assertTrue(array_key_exists($varName, $hookVars), 'Expected variable ' . $varName);
            }
        }
    }
}
