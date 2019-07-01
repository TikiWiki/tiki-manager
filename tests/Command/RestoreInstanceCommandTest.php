<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TikiManager\Command\RestoreInstanceCommand;
use TikiManager\Application\Instance;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Libs\Database\Database;
use PHPUnit\Framework\TestCase;
use DBDiff;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class RestoreInstanceCommandTest
 * @group Commands
 * @backupGlobals true
 */
class RestoreInstanceCommandTest extends TestCase
{
    private static $instanceBasePath;
    private static $tempPath;
    private static $instanceIds = [];
    private static $dbLocalFileTrunk;
    private static $instancePathTrunk;
    private static $dbLocalFileBlankToTrunk;
    private static $instancePathBlank;

    public static function setUpBeforeClass()
    {
        $basePath = $_ENV['TESTS_BASE_FOLDER'];
        self::$instanceBasePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'restore']);
        self::$tempPath = implode(DIRECTORY_SEPARATOR, [$basePath, 'manager']);

        // instances paths
        self::$instancePathTrunk = implode(DIRECTORY_SEPARATOR, [self::$instanceBasePath, 'instance1']);
        self::$instancePathBlank = implode(DIRECTORY_SEPARATOR, [self::$instanceBasePath, 'instance2']);

        self::$dbLocalFileTrunk = implode(DIRECTORY_SEPARATOR, [self::$instancePathTrunk, 'db', 'local.php']);
        self::$dbLocalFileBlankToTrunk = implode(DIRECTORY_SEPARATOR, [self::$instancePathBlank, 'db', 'local.php']);

        $instanceId = InstanceHelper::create([
            InstanceHelper::WEBROOT_OPTION => self::$instancePathTrunk,
            InstanceHelper::TEMPDIR_OPTION => self::$tempPath,
        ]);

        self::$instanceIds[] = $instanceId;

        $instance = Instance::getInstance(self::$instanceIds[0]);
        $instance->backup();

        $instanceId = InstanceHelper::create([
            InstanceHelper::NAME_OPTION => 'blank.tiki.org',
            InstanceHelper::WEBROOT_OPTION => self::$instancePathBlank,
            InstanceHelper::TEMPDIR_OPTION => self::$tempPath,
        ], true);

        self::$instanceIds[] = $instanceId;
    }

    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(self::$instanceBasePath);
    }

    public function testRestoreLocalInstance()
    {
        $this->assertNotFalse(self::$instanceIds[0]);
        $this->assertNotFalse(self::$instanceIds[1]);

        $application = new Application();
        $application->add(new RestoreInstanceCommand());

        $command = $application->find('instance:restore');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs([
            self::$instanceIds[1], // blank instance
            self::$instanceIds[0], // trunk instance
            0, // first backup in list (last backup)
            // Database ROOT credentials are passed in $_ENV
            'yes', // DB create database and user
            substr(md5(random_bytes(5)), 0, 8) // DB prefix
        ]);

        $commandTester->execute([
                'command'  => $command->getName()
        ]);

        $output = $commandTester->getDisplay();
        $this->assertContains('It is now time to test your site: blank.tiki.org', $output);
        $this->assertContains('If there are issues, connect with make access to troubleshoot directly on the server.', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertFileExists(self::$dbLocalFileBlankToTrunk);
    }

    /**
     * @depends testRestoreLocalInstance
     */
    public function testDiffDataBase()
    {
        $this->assertFileExists(self::$dbLocalFileTrunk);
        $this->assertFileExists(self::$dbLocalFileBlankToTrunk);

        $fileSystem = new Filesystem();
        if ($fileSystem->exists(self::$dbLocalFileTrunk) && $fileSystem->exists(self::$dbLocalFileBlankToTrunk)) {
            $trunkConfig = Database::getInstanceDataBaseConfig(self::$dbLocalFileTrunk);
            $blankToTrunkConfig = Database::getInstanceDataBaseConfig(self::$dbLocalFileBlankToTrunk);

            $host = $_ENV['DB_HOST']; // DB Host
            $user = $_ENV['DB_USER']; // DB Root User
            $pass = $_ENV['DB_PASS']; // DB Root Password
            $port = $_ENV['DB_PORT'] ?? '3306';

            $db1 = $trunkConfig['dbname'];
            $db2 = $blankToTrunkConfig['dbname'];

            $GLOBALS['argv'] = [
                "",
                "--server1=$user:$pass@$host:$port",
                "--type=data",
                "--include=all", // no UP or DOWN will be used
                "--nocomments",
                "server1.$db1:server1.$db2"
            ];

            ob_start();
            $dbdiff = new DBDiff\DBDiff;
            $dbdiff->run();
            $output = ob_get_contents();
            ob_end_clean();

            $this->assertContains('Identical resources', $output);
            $this->assertContains('Completed', $output);
        }
    }
}
