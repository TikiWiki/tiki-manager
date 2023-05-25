<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use PHPUnit\Framework\TestCase;
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
class CloneInstanceCommandTest extends TestCase
{
    protected static $instanceType;
    protected static $instancePath;
    protected static $instancePaths = [];
    protected static $dbLocalFiles = [];
    protected static $instanceIds = [];
    protected static $ListCommandInput = [];

    protected static $dbConfig = [];

    protected static $prevVersionBranch;

    public static function setUpBeforeClass(): void
    {
        static::$instanceType = getenv('TEST_INSTANCE_TYPE') ?: 'local';

        $isSrc = strtoupper($_ENV['DEFAULT_VCS']) === 'SRC';
        static::$prevVersionBranch = $isSrc ? $_ENV['PREV_SRC_MAJOR_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        self::$instancePath = $_ENV['TESTS_BASE_FOLDER'] . '/clone';

        $sshOptions = [
            InstanceHelper::TYPE_OPTION => 'ssh',
            InstanceHelper::HOST_NAME_OPTION => $_ENV['SSH_HOST_NAME'],
            InstanceHelper::HOST_PORT_OPTION => $_ENV['SSH_HOST_PORT'] ?? 22,
            InstanceHelper::HOST_USER_OPTION => $_ENV['SSH_HOST_USER'],
            InstanceHelper::HOST_PASS_OPTION => $_ENV['SSH_HOST_PASS'] ?? null,
        ];

        $instanceNames = [
            'source' => [],
            'target' => [],
            'target2' => [],
            'blank' => [[], true]
        ];

        foreach ($instanceNames as $instanceName => $options) {
            self::$instancePaths[$instanceName] = implode(DIRECTORY_SEPARATOR, [self::$instancePath, $instanceName]);
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

            if (static::$instanceType == 'ssh') {
                $sourceDetails = array_merge($sourceDetails, $sshOptions);
            }

            self::$instanceIds[$instanceName] = InstanceHelper::create($sourceDetails, isset($options[1]));

            if ($instanceName != 'blank') {
                $instance = Instance::getInstance(self::$instanceIds[$instanceName]);
                $configFile = $instance->getBestAccess()->downloadFile(self::$dbLocalFiles[$instanceName]);
                self::$dbConfig[$instanceName] = file_get_contents($configFile);
                unlink($configFile);
            }
        }
    }

    protected function tearDown(): void
    {
        $this->restoreDBConfigFiles();
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
        $fs->remove(self::$instancePath);
    }

    protected function restoreDBConfigFiles()
    {
        foreach (static::$dbConfig as $instanceName => $fileContent) {
            $filePath = static::$dbLocalFiles[$instanceName];
            $instance = Instance::getInstance(static::$instanceIds[$instanceName]);
            $access = $instance->getBestAccess();
            $access->deleteFile($filePath);

            $file = tempnam($_ENV['TEMP_FOLDER'], '');
            file_put_contents($file, $fileContent);

            $access->uploadFile($file, $filePath);
            unlink($file);

            $this->assertTrue($access->fileExists($filePath));
        }
    }

    public function testCloneInstance()
    {
        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments);
        $this->assertTrue($result['exitCode'] === 0);

        $instance = (new Instance())->getInstance(self::$instanceIds['target']);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        if (static::$instanceType == 'local') {
            $diffDbFile = Files::compareFiles(self::$dbLocalFiles['source'], self::$dbLocalFiles['target']);

            $this->assertEquals(VersionControl::formatBranch(static::$prevVersionBranch), $resultBranch);
            $this->assertNotEquals([], $diffDbFile);

            $this->compareDB('source', 'target');
        }
    }

    public function testCloneSameDatabase()
    {
        $sourceInstance = Instance::getInstance(static::$instanceIds['source']);
        $targetInstance = Instance::getInstance(static::$instanceIds['target']);

        $this->assertTrue($sourceInstance->getBestAccess()->fileExists(static::$dbLocalFiles['source']));
        $sourceConfig = $sourceInstance->getBestAccess()->downloadFile(static::$dbLocalFiles['source']);

        $targetInstance->getBestAccess()->deleteFile(static::$dbLocalFiles['target']);
        $targetInstance->getBestAccess()->uploadFile($sourceConfig, static::$dbLocalFiles['target']);
        unlink($sourceConfig);

        $this->assertTrue($targetInstance->getBestAccess()->fileExists(static::$dbLocalFiles['target']));

        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] !== 0);
        $this->assertStringContainsString('Database host and name are the same', $result['output']);
    }

    public function testCloneDatabaseWithTargetMissingDbFile()
    {
        $sourceInstance = Instance::getInstance(static::$instanceIds['source']);
        $sourceAccess = $sourceInstance->getBestAccess();
        $this->assertTrue($sourceAccess->fileExists(static::$dbLocalFiles['source']));

        $targetInstance = Instance::getInstance(static::$instanceIds['target']);
        $targetAccess = $targetInstance->getBestAccess();
        $targetAccess->deleteFile(static::$dbLocalFiles['target']);
        $this->assertFalse($targetAccess->fileExists(static::$dbLocalFiles['target']));

        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] !== 0);
        $output = $result['output'];

        $this->assertStringContainsString('Database configuration file not found', $output);
        $this->assertStringContainsString('Unable to load/set database configuration for instance', $output);
    }

    public function testCloneDatabaseTargetManyInstances()
    {
        $varPrefix = static::$instanceType == 'local' ? '' : strtoupper(static::$instanceType) . '_';

        $host = getenv($varPrefix . 'DB_HOST'); // DB Host
        $user = getenv($varPrefix . 'DB_USER'); // DB Root User
        $pass = getenv($varPrefix . 'DB_PASS'); // DB Root Password

        $targetDBName = InstanceHelper::getRandomDbName();
        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target']), strval(self::$instanceIds['target2'])],
            '--db-host' => $host,
            '--db-user' => $user,
            '--db-pass' => $pass,
            '--db-name' => $targetDBName,
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertEquals(1, $result['exitCode']);
        $this->assertStringContainsString(
            'Database setup options can only be used when a single target instance',
            $result['output']
        );
    }

    public function testCloneDatabaseTargetBlank()
    {
        $varPrefix = static::$instanceType == 'local' ? '' : strtoupper(static::$instanceType) . '_';

        $host = getenv($varPrefix . 'DB_HOST'); // DB Host
        $user = getenv($varPrefix . 'DB_USER'); // DB Root User
        $pass = getenv($varPrefix . 'DB_PASS'); // DB Root Password

        $targetDBName = substr(md5(random_bytes(5)), 0, 8);
        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['blank'])],
            '--db-host' => $host,
            '--db-user' => $user,
            '--db-pass' => $pass,
            '--db-prefix' => $targetDBName,
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertEquals(0, $result['exitCode'], $result['output']);

        $instance = (new Instance())->getInstance(self::$instanceIds['blank']);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();
        $this->assertEquals(VersionControl::formatBranch(static::$prevVersionBranch), $resultBranch);

        if (static::$instanceType == 'local') {
            $diffDbFile = Files::compareFiles(self::$dbLocalFiles['source'], self::$dbLocalFiles['blank']);

            $this->assertNotEquals([], $diffDbFile);
            $dbCnf = $instance->getDatabaseConfig();
            $this->assertEquals($arguments['--db-host'], $dbCnf->host);
            $this->assertStringContainsString($arguments['--db-prefix'], $dbCnf->user);
            $this->assertStringContainsString($arguments['--db-prefix'], $dbCnf->dbname);

            $this->compareDB('source', 'blank');
        }
    }

    public function testCloneNewTargetDB()
    {
        $varPrefix = static::$instanceType == 'local' ? '' : strtoupper(static::$instanceType) . '_';

        $host = getenv($varPrefix . 'DB_HOST'); // DB Root Host
        $user = getenv($varPrefix . 'DB_USER'); // DB Root User
        $pass = getenv($varPrefix . 'DB_PASS'); // DB Root Password

        $targetDBName = InstanceHelper::getRandomDbName();
        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--db-host' => $host,
            '--db-user' => $user,
            '--db-pass' => $pass,
            '--db-name' => $targetDBName,
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertEquals(0, $result['exitCode']);

        $instance = (new Instance())->getInstance(self::$instanceIds['target']);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();
        $this->assertEquals(VersionControl::formatBranch(static::$prevVersionBranch), $resultBranch);

        if (static::$instanceType == 'local') {
            $diffDbFile = Files::compareFiles(self::$dbLocalFiles['source'], self::$dbLocalFiles['target']);
            $this->assertNotEquals([], $diffDbFile);

            $dbCnf = $instance->getDatabaseConfig();
            $this->assertEquals($_ENV['DB_HOST'], $dbCnf->host);
            $this->assertEquals($_ENV['DB_USER'], $dbCnf->user);
            $this->assertEquals($_ENV['DB_PASS'], $dbCnf->pass);
            $this->assertEquals($arguments['--db-name'], $dbCnf->dbname);

            $this->compareDB('source', 'target');
        }
    }

    public function testCloneInvalidNewTargetDB()
    {
        $varPrefix = static::$instanceType == 'local' ? '' : strtoupper(static::$instanceType) . '_';

        $user = getenv($varPrefix . 'DB_USER'); // DB Root User
        $pass = getenv($varPrefix . 'DB_PASS'); // DB Root Password

        $targetDBName = InstanceHelper::getRandomDbName();
        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--db-host' => 'error_host',
            '--db-user' => $user,
            '--db-pass' => $pass,
            '--db-name' => $targetDBName,
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, false, ['interactive' => false]);
        $this->assertEquals(1, $result['exitCode']);
        $this->assertStringContainsString('Unable to access database', $result['output']);
    }

    protected function compareDB($instance1, $instance2)
    {
        if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
            // dbdiff tool does not support PHP8.1
            return;
        }

        $fileSystem = new Filesystem();
        if ($fileSystem->exists(self::$dbLocalFiles[$instance1]) &&
            $fileSystem->exists(self::$dbLocalFiles[$instance2])) {
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
            $this->assertStringContainsString('Identical resources', $output);
            $this->assertStringContainsString('Completed', $output);
        }
    }
}
