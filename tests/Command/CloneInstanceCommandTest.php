<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use TikiManager\Application\Instance;
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
    static $instancePaths = [];
    static $dbLocalFiles = [];
    static $instanceIds = [];
    static $ListCommandInput = [];

    static $dbConfig = [];

    static $prevVersionBranch;

    public static function setUpBeforeClass()
    {
        if (strtoupper($_ENV['DEFAULT_VCS']) === 'SRC') {
            static::$prevVersionBranch = $_ENV['PREV_SRC_MAJOR_RELEASE'];
        } else {
            static::$prevVersionBranch = $_ENV['PREV_VERSION_BRANCH'];
        }

        $basePath = $_ENV['TESTS_BASE_FOLDER'] . '/clone';
        self::$instancePath = $basePath;

        $instanceNames = [
            'source' => [],
            'target' => [],
            'target2' => [],
            'blank' => [[], true]
        ];

        foreach ($instanceNames as $instanceName => $options) {
            self::$instancePaths[$instanceName] = implode(DIRECTORY_SEPARATOR, [$basePath, $instanceName]);
            self::$dbLocalFiles[$instanceName] = implode(DIRECTORY_SEPARATOR, [self::$instancePaths[$instanceName], 'db', 'local.php']);

            $sourceDetails = [
                InstanceHelper::WEBROOT_OPTION => self::$instancePaths[$instanceName],
                InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch(self::$prevVersionBranch),
                InstanceHelper::URL_OPTION => 'http://' . $instanceName . '-test.tiki.org',
                InstanceHelper::NAME_OPTION => $instanceName . '-test.tiki.org',
            ];

            if (isset($options[0]) && is_array($options[0])) {
                $sourceDetails = array_merge($sourceDetails, $options[0]);
            }
            self::$instanceIds[$instanceName] = InstanceHelper::create($sourceDetails, isset($options[1]));

            if ($instanceName != 'blank') {
                self::$dbConfig[$instanceName] = file_get_contents(self::$dbLocalFiles[$instanceName]);
            }
        }
    }

    public function setUp() {
        $this->restoreDBConfigFiles();
    }

    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(self::$instancePath);

        foreach(self::$instanceIds as $instanceId) {
            $instance = Instance::getInstance($instanceId);
            $instance->delete();
        }
    }

    protected function restoreDBConfigFiles() {
        $fs = new Filesystem();
        foreach(self::$dbConfig as $instanceName => $fileContent) {
            $file = self::$dbLocalFiles[$instanceName];
            $fs->remove($file);
            $fs->appendToFile($file, $fileContent);
        }
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

        $diffDbFile = Files::compareFiles(self::$dbLocalFiles['source'], self::$dbLocalFiles['target']);

        $this->assertEquals(VersionControl::formatBranch(static::$prevVersionBranch), $resultBranch);
        $this->assertNotEquals([], $diffDbFile);

        $this->compareDB('source', 'target');
    }

    public function successCloneCombinations(): array
    {
        return [
            ['direct' => false],
            ['direct' => true],
        ];
    }

    public function testCloneSameDatabase()
    {
        $this->assertFileExists(self::$dbLocalFiles['source']);
        $fileSystem = new Filesystem();
        $fileSystem->copy(self::$dbLocalFiles['source'], self::$dbLocalFiles['target'], true);

        $this->assertFileExists(self::$dbLocalFiles['target']);
        $diffDbFile = Files::compareFiles(self::$dbLocalFiles['source'], self::$dbLocalFiles['target']);
        $this->assertEquals([], $diffDbFile);

        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] !== 0);
        $this->assertContains('Database host and name are the same', $result['output']);
    }

    public function testCloneDatabaseWithTargetMissingDbFile()
    {
        $this->assertFileExists(self::$dbLocalFiles['source']);
        $fileSystem = new Filesystem();
        $fileSystem->remove(self::$dbLocalFiles['target']);

        $this->assertFileNotExists(self::$dbLocalFiles['target']);

        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] !== 0);
        $output = $result['output'];

        $this->assertContains('Database configuration file not found', $output);
        $this->assertContains('Unable to load/set database configuration for instance', $output);
    }

    public function testCloneDatabaseTargetManyInstances()
    {
        $targetDBName = InstanceHelper::getRandomDbName();
        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target']), strval(self::$instanceIds['target2'])],
            '--db-host' => $_ENV['DB_HOST'],
            '--db-user' => $_ENV['DB_USER'],
            '--db-pass' => $_ENV['DB_PASS'],
            '--db-name' => $targetDBName,
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertEquals(1, $result['exitCode']);
        $this->assertContains('Database setup options can only be used when a single target instance', $result['output']);
    }

    public function testCloneDatabaseTargetBlank()
    {
        $targetDBName = InstanceHelper::getRandomDbName();
        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['blank'])],
            '--db-host' => $_ENV['DB_HOST'],
            '--db-user' => $_ENV['DB_USER'],
            '--db-pass' => $_ENV['DB_PASS'],
            '--db-prefix' => $targetDBName,
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertEquals(0, $result['exitCode']);

        $instance = (new Instance())->getInstance(self::$instanceIds['blank']);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $diffDbFile = Files::compareFiles(self::$dbLocalFiles['source'], self::$dbLocalFiles['blank']);

        $this->assertEquals(VersionControl::formatBranch(static::$prevVersionBranch), $resultBranch);
        $this->assertNotEquals([], $diffDbFile);
        $dbCnf = $instance->getDatabaseConfig();
        self::assertEquals($arguments['--db-host'], $dbCnf->host);
        self::assertContains($arguments['--db-prefix'], $dbCnf->user);
        self::assertContains($arguments['--db-prefix'], $dbCnf->dbname);

        $this->compareDB('source', 'blank');
    }

    public function testCloneNewTargetDB()
    {
        $targetDBName = InstanceHelper::getRandomDbName();
        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--db-name' => $targetDBName,
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertEquals(0, $result['exitCode']);

        $instance = (new Instance())->getInstance(self::$instanceIds['target']);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $diffDbFile = Files::compareFiles(self::$dbLocalFiles['source'], self::$dbLocalFiles['target']);

        $this->assertEquals(VersionControl::formatBranch(static::$prevVersionBranch), $resultBranch);
        $this->assertNotEquals([], $diffDbFile);

        $dbCnf = $instance->getDatabaseConfig();
        self::assertEquals($_ENV['DB_HOST'], $dbCnf->host);
        self::assertEquals($_ENV['DB_USER'], $dbCnf->user);
        self::assertEquals($_ENV['DB_PASS'], $dbCnf->pass);
        self::assertEquals($arguments['--db-name'], $dbCnf->dbname);

        $this->compareDB('source', 'target');
    }

    public function testCloneInvalidNewTargetDB()
    {
        $targetDBName = InstanceHelper::getRandomDbName();
        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--db-host' => 'error_host',
            '--db-user' => $_ENV['DB_USER'],
            '--db-pass' => $_ENV['DB_PASS'],
            '--db-name' => $targetDBName,
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertEquals(1, $result['exitCode']);
        $this->assertContains('Unable to access database', $result['output']);
    }

    public function compareDB($instance1, $instance2)
    {
        $fileSystem = new Filesystem();
        if ($fileSystem->exists(self::$dbLocalFiles[$instance1]) && $fileSystem->exists(self::$dbLocalFiles[$instance2])) {
            $sourceDB = Database::getInstanceDataBaseConfig(self::$dbLocalFiles[$instance1]);
            $targetDB = Database::getInstanceDataBaseConfig(self::$dbLocalFiles[$instance2]);

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
