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
use Symfony\Component\Process\Process;
use TikiManager\Application\Instance;
use TikiManager\Command\CloneInstanceCommand;
use TikiManager\Libs\Database\Database;
use TikiManager\Libs\Helpers\VersionControl;
use TikiManager\Tests\Helpers\Files;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class CloneInstanceCommandTest
 * @group Commands
 * @backupGlobals true
 */
class CloneInstanceCommandTest extends \PHPUnit\Framework\TestCase
{
    static $instancePath;
    static $sourceInstancePath;
    static $targetInstancePath;
    static $dbLocalFile1;
    static $dbLocalFile2;
    static $instanceIds = [];
    static $ListCommandInput = [];

    static $prevVersionBranch;

    public static function setUpBeforeClass()
    {
        if (strtoupper($_ENV['DEFAULT_VCS']) === 'SRC') {
            static::$prevVersionBranch = $_ENV['PREV_SRC_MAJOR_RELEASE'];
            $branch = $_ENV['LATEST_SRC_RELEASE'];
        } else {
            static::$prevVersionBranch = $_ENV['PREV_VERSION_BRANCH'];
            $branch = $_ENV['MASTER_BRANCH'];
        }

        $basePath = $_ENV['TESTS_BASE_FOLDER'] . '/clone';

        self::$instancePath = $basePath;
        self::$sourceInstancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'source']);
        self::$targetInstancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'target']);
        self::$dbLocalFile1 = implode(DIRECTORY_SEPARATOR, [self::$sourceInstancePath, 'db', 'local.php']);
        self::$dbLocalFile2 = implode(DIRECTORY_SEPARATOR, [self::$targetInstancePath, 'db', 'local.php']);

        $sourceDetails = [
            InstanceHelper::WEBROOT_OPTION => self::$sourceInstancePath,
            InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch(static::$prevVersionBranch),
            InstanceHelper::URL_OPTION => 'http://source-test.tiki.org',
            InstanceHelper::NAME_OPTION => 'source-test.tiki.org',
        ];

        $targetDetails = [
            InstanceHelper::WEBROOT_OPTION => self::$targetInstancePath,
            InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch($branch),
            InstanceHelper::URL_OPTION => 'http://target-test.tiki.org',
            InstanceHelper::NAME_OPTION => 'target-test.tiki.org',
        ];

        $sourceInstanceId = InstanceHelper::create($sourceDetails);
        $targetInstanceId = InstanceHelper::create($targetDetails);

        self::$instanceIds['source'] = $sourceInstanceId;
        self::$instanceIds['target'] = $targetInstanceId;
    }

    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(self::$instancePath);

        $instance = Instance::getInstance(self::$instanceIds['source']);
        $instance->delete();

        $instance = Instance::getInstance(self::$instanceIds['target']);
        $instance->delete();
    }

    /**
     * @param $direct
     * @dataProvider successCloneCombinations
     */
    public function testLocalCloneInstance($direct)
    {
        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--skip-cache-warmup' => true,
        ];

        if ($direct) {
            $arguments['--direct'] = true;
        }

        $result = InstanceHelper::clone($arguments);
        $this->assertTrue($result['exitCode'] === 0);

        $instance = (new Instance())->getInstance(self::$instanceIds['target']);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $diffDbFile = Files::compareFiles(self::$dbLocalFile1, self::$dbLocalFile2);

        $this->assertEquals(VersionControl::formatBranch(static::$prevVersionBranch), $resultBranch);
        $this->assertNotEquals([], $diffDbFile);

        $this->compareDB();
    }

    public function successCloneCombinations():array
    {
        return [
            ['direct' => false],
            ['direct' => true],
        ];
    }

    public function testCloneSameDatabase()
    {
        $this->assertFileExists(self::$dbLocalFile1);
        $fileSystem = new Filesystem();
        $fileSystem->copy(self::$dbLocalFile1, self::$dbLocalFile2, true);

        $this->assertFileExists(self::$dbLocalFile2);
        $diffDbFile = Files::compareFiles(self::$dbLocalFile1, self::$dbLocalFile2);
        $this->assertEquals([], $diffDbFile);

        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--skip-cache-warmup' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] !== 0);
        $this->assertContains('Database host and name are the same', $result['output']);
    }

    public function testCloneDatabaseWithTargetMissingDbFile()
    {
        $this->assertFileExists(self::$dbLocalFile1);
        $fileSystem = new Filesystem();
        $fileSystem->remove(self::$dbLocalFile2);

        $this->assertFileNotExists(self::$dbLocalFile2);

        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--skip-cache-warmup' => true
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] !== 0);
        $output = $result['output'];

        $this->assertContains('Database configuration file not found', $output);
        $this->assertContains('Unable to load/set database configuration for instance', $output);
    }

    public function compareDB()
    {
        $fileSystem = new Filesystem();
        if ($fileSystem->exists(self::$dbLocalFile1) && $fileSystem->exists(self::$dbLocalFile2)) {
            $sourceDB = Database::getInstanceDataBaseConfig(self::$dbLocalFile1);
            $targetDB = Database::getInstanceDataBaseConfig(self::$dbLocalFile2);

            $host = getenv('DB_HOST'); // DB Host
            $user = getenv('DB_USER'); // DB Root User
            $pass = getenv('DB_PASS'); // DB Root Password
            $port = getenv('DB_PORT') ?? '3306';

            $db1 = $sourceDB['dbname'];
            $db2 = $targetDB['dbname'];

            // This command cannot be changed due to dbdiff require autoload path
            $command = [
                "vendor/dbdiff/dbdiff/dbdiff",
                "--server1=$user:$pass@$host:$port",
                "--type=data",
                "--include=all", // no UP or DOWN will be used
                "--nocomments",
                "server1.$db1:server1.$db2"
            ];

            $process = new Process($command, $_ENV['TRIM_ROOT'] . '/vendor-bin/dbdiff');
            $process->setTimeout(0);
            $process->run();

            $this->assertEquals(0, $process->getExitCode());
            $output = $process->getOutput();
            $this->assertContains('Identical resources', $output);
            $this->assertContains('Completed', $output);
        }
    }
}
