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
use TikiManager\Application\Instance;
use TikiManager\Command\CloneInstanceCommand;
use TikiManager\Tests\Helpers\Files;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;
use TikiManager\Tests\Helpers\VersionControl;

/**
 * Class CloneInstanceCommandTester
 * @group Commands
 * @backupGlobals true
 */
class CloneInstanceCommandTester extends \PHPUnit\Framework\TestCase
{
    static $instancePath;
    static $tempPath;
    static $instancePath19x;
    static $instancePathTrunk;
    static $dbLocalFile19x;
    static $dbLocalFileTrunk;
    static $instanceIds = [];
    static $ListCommandInput = [];

    public static function setUpBeforeClass()
    {
        $basePath = $_ENV['TESTS_BASE_FOLDER'];
        $scriptOwner = get_current_user();

        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'clone']);
        self::$tempPath = implode(DIRECTORY_SEPARATOR, [$basePath, 'manager']);
        self::$instancePath19x = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'instance1']);
        self::$instancePathTrunk = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'instance2']);
        self::$dbLocalFile19x = implode(DIRECTORY_SEPARATOR, [self::$instancePath19x, 'db', 'local.php']);
        self::$dbLocalFileTrunk = implode(DIRECTORY_SEPARATOR, [self::$instancePathTrunk, 'db', 'local.php']);

        self::$ListCommandInput = [
            [
                InstanceHelper::WEBROOT_OPTION => self::$instancePath19x,
                InstanceHelper::TEMPDIR_OPTION => self::$tempPath,
                InstanceHelper::BACKUP_USER_OPTION => isset($scriptOwner) ? $scriptOwner : 'root', // Backup user
                InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch('branches/19.x'), // svn : branches/19.x
            ],
            [
                InstanceHelper::WEBROOT_OPTION => self::$instancePathTrunk,
                InstanceHelper::TEMPDIR_OPTION => self::$tempPath,
                InstanceHelper::BACKUP_USER_OPTION => isset($scriptOwner) ? $scriptOwner : 'root', // Backup user
                InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch('trunk'), // svn : branches/19.x
            ]
        ];
    }

    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(self::$instancePath);
    }

    public function testLocalCloneInstance()
    {
        $count = 1;
        foreach (self::$ListCommandInput as $commandInput) {
            $instanceId = InstanceHelper::create($commandInput);
            $this->assertNotFalse($instanceId);
            self::$instanceIds[$count] = $instanceId;
            $count++;
        }

        // Clone command
        $application = new Application();
        $application->add(new CloneInstanceCommand());
        $command = $application->find('instance:clone');
        $commandTester = new CommandTester($command);

        $arguments = [
            'command' => 'instance:clone',
            '--source' => strval(self::$instanceIds[1]),
            '--target' => [strval(self::$instanceIds[2])]
        ];

        $commandTester->execute($arguments);

        $instance = new Instance;
        $instance = $instance->getInstance(self::$instanceIds[2]);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $diffDbFile = Files::compareFiles(self::$dbLocalFile19x, self::$dbLocalFileTrunk);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(VersionControl::formatBranch('branches/19.x'), $resultBranch);
        $this->assertNotEquals([], $diffDbFile);
    }

    /**
     * @depends testLocalCloneInstance
     */
    public function testCloneSameDatabase()
    {
        $fileSystem = new Filesystem();
        if ($fileSystem->exists(self::$dbLocalFile19x)) {
            $fileSystem->copy(self::$dbLocalFile19x, self::$dbLocalFileTrunk, true);
        }

        // Clone command
        $application = new Application();
        $application->add(new CloneInstanceCommand());
        $command = $application->find('instance:clone');
        $commandTester = new CommandTester($command);

        $arguments = [
            'command' => 'instance:clone',
            '--source' => strval(self::$instanceIds[1]),
            '--target' => [strval(self::$instanceIds[2])]
        ];

        $commandTester->execute($arguments);

        $output = $commandTester->getDisplay();

        $this->assertContains('Database host and name are the same', $output);
    }
}
