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
use TikiManager\Libs\Helpers\VersionControl;
use TikiManager\Tests\Helpers\Files;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class CloneInstanceCommandTester
 * @group Commands
 * @backupGlobals true
 */
class CloneInstanceCommandTester extends \PHPUnit\Framework\TestCase
{
    static $instancePath;
    static $instancePath1;
    static $instancePath2;
    static $dbLocalFile1;
    static $dbLocalFile2;
    static $instanceIds = [];
    static $ListCommandInput = [];

    public static function setUpBeforeClass()
    {
        if (strtoupper($_ENV['DEFAULT_VCS']) === 'SRC') {
            $prevVersionBranch = $_ENV['PREV_SRC_MAJOR_RELEASE'];
            $branch = $_ENV['LATEST_SRC_RELEASE'];
        } else {
            $prevVersionBranch = $_ENV['PREV_VERSION_BRANCH'];
            $branch = $_ENV['MASTER_BRANCH'];
        }

        $basePath = $_ENV['TESTS_BASE_FOLDER'];
        $scriptOwner = get_current_user();

        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'clone']);
        self::$instancePath1 = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'instance1']);
        self::$instancePath2 = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'instance2']);
        self::$dbLocalFile1 = implode(DIRECTORY_SEPARATOR, [self::$instancePath1, 'db', 'local.php']);
        self::$dbLocalFile2 = implode(DIRECTORY_SEPARATOR, [self::$instancePath2, 'db', 'local.php']);

        self::$ListCommandInput = [
            [
                InstanceHelper::WEBROOT_OPTION => self::$instancePath1,
                InstanceHelper::BACKUP_USER_OPTION => isset($scriptOwner) ? $scriptOwner : 'root', // Backup user
                InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch($prevVersionBranch),
            ],
            [
                InstanceHelper::WEBROOT_OPTION => self::$instancePath2,
                InstanceHelper::BACKUP_USER_OPTION => isset($scriptOwner) ? $scriptOwner : 'root', // Backup user
                InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch($branch),
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
        $prevVersionBranch = strtoupper($_ENV['DEFAULT_VCS']) === 'SRC' ? $_ENV['PREV_SRC_MAJOR_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        $count = 1;
        foreach (self::$ListCommandInput as $commandInput) {
            $instanceId = InstanceHelper::create($commandInput);
            $this->assertNotFalse($instanceId);
            self::$instanceIds[$count] = $instanceId;
            $count++;
        }

        $arguments = [
            '--source' => strval(self::$instanceIds[1]),
            '--target' => [strval(self::$instanceIds[2])],
            '--skip-cache-warmup' => true,
        ];

        // Clone command
        $result = InstanceHelper::clone($arguments);
        $this->assertTrue($result);

        $instance = (new Instance())->getInstance(self::$instanceIds[2]);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $diffDbFile = Files::compareFiles(self::$dbLocalFile1, self::$dbLocalFile2);

        $this->assertEquals(VersionControl::formatBranch($prevVersionBranch), $resultBranch);
        $this->assertNotEquals([], $diffDbFile);
    }

    /**
     * @depends testLocalCloneInstance
     */
    public function testCloneSameDatabase()
    {
        $fileSystem = new Filesystem();
        if ($fileSystem->exists(self::$dbLocalFile1)) {
            $fileSystem->copy(self::$dbLocalFile1, self::$dbLocalFile2, true);
        }

        // Clone command
        $application = new Application();
        $application->add(new CloneInstanceCommand());
        $command = $application->find('instance:clone');
        $commandTester = new CommandTester($command);

        $arguments = [
            'command' => 'instance:clone',
            '--source' => strval(self::$instanceIds[1]),
            '--target' => [strval(self::$instanceIds[2])],
            '--skip-cache-warmup' => true,
        ];

        $commandTester->execute($arguments);

        $output = $commandTester->getDisplay();

        $this->assertContains('Database host and name are the same', $output);
    }
}
