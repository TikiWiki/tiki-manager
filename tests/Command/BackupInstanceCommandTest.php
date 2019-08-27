<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Command\BackupInstanceCommand;
use TikiManager\Application\Instance;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class BackupInstanceCommandTest
 * @group Commands
 * @backupGlobals true
 */
class BackupInstanceCommandTest extends TestCase
{
    private static $instanceId;
    private static $instanceBasePath;
    private static $instance1Path;
    private static $tempPath;
    private static $archiveDir;

    public static function setUpBeforeClass()
    {
        $basePath = $_ENV['TESTS_BASE_FOLDER'];
        $scriptOwner = get_current_user();

        self::$archiveDir = rtrim($_ENV['ARCHIVE_FOLDER'], '/');

        self::$instanceBasePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'backup']);
        self::$tempPath = implode(DIRECTORY_SEPARATOR, [$basePath, 'manager']);
        self::$instance1Path = implode(DIRECTORY_SEPARATOR, [self::$instanceBasePath, 'instance1']);

        self::$instanceId = InstanceHelper::create([
            InstanceHelper::WEBROOT_OPTION => self::$instance1Path,
            InstanceHelper::TEMPDIR_OPTION => self::$tempPath,
            InstanceHelper::BACKUP_USER_OPTION => isset($scriptOwner) ? $scriptOwner : 'root', // Backup user
        ]);
    }

    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(self::$instanceBasePath);
    }

    public function testBackupLocalInstance()
    {
        // Ensure that instance was created successfully
        $this->assertNotFalse(self::$instanceId);

        $application = new Application();
        $application->add(new BackupInstanceCommand());

        $command = $application->find('instance:backup');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command'  => $command->getName(),
            '--instances' => self::$instanceId,
        ], ['interactive' => false]);

        $output = $commandTester->getDisplay();
        $this->assertContains('Backup created with success.', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * @depends testBackupLocalInstance
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
        $instance = Instance::getInstance(self::$instanceId);
        $archivePath = self::$archiveDir . DIRECTORY_SEPARATOR . $instance->id . '-' . $instance->name;
        return $archivePath;
    }
}
