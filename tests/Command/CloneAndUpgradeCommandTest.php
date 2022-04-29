<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Application\Instance;
use TikiManager\Libs\Helpers\VersionControl;
use TikiManager\Tests\Helpers\Files;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class CloneInstanceCommandTest
 * @group Commands
 * @backupGlobals true
 */
class CloneAndUpgradeCommandTest extends TestCase
{
    protected static $instanceType;
    protected static $instancePath;
    protected static $sourceInstancePath;
    protected static $targetInstancePath;
    protected static $dbSourceFile;
    protected static $dbTargetFile;
    protected static $instanceIds = [];

    public static function setUpBeforeClass()
    {
        static::$instanceType = getenv('TEST_INSTANCE_TYPE') ?: 'local';
        $basePath = $_ENV['TESTS_BASE_FOLDER'];

        static::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'cloneupgrade']);
        static::$sourceInstancePath = implode(DIRECTORY_SEPARATOR, [static::$instancePath, 'source']);
        static::$targetInstancePath = implode(DIRECTORY_SEPARATOR, [static::$instancePath, 'target']);
        static::$dbSourceFile = implode(DIRECTORY_SEPARATOR, [static::$sourceInstancePath, 'db', 'local.php']);
        static::$dbTargetFile = implode(DIRECTORY_SEPARATOR, [static::$targetInstancePath, 'db', 'local.php']);

        $vcs = strtoupper($_ENV['DEFAULT_VCS']);
        $branch = $vcs === 'SRC' ? $_ENV['PREV_SRC_MAJOR_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        $sshOptions = [
            InstanceHelper::TYPE_OPTION => 'ssh',
            InstanceHelper::HOST_NAME_OPTION => $_ENV['SSH_HOST_NAME'],
            InstanceHelper::HOST_PORT_OPTION => $_ENV['SSH_HOST_PORT'] ?? 22,
            InstanceHelper::HOST_USER_OPTION => $_ENV['SSH_HOST_USER'],
            InstanceHelper::HOST_PASS_OPTION => $_ENV['SSH_HOST_PASS'] ?? null,
        ];

        $sourceDetails = [
            InstanceHelper::WEBROOT_OPTION => static::$sourceInstancePath,
            InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch($branch),
            InstanceHelper::URL_OPTION => 'http://source-test.tiki.org',
            InstanceHelper::NAME_OPTION => 'source-test.tiki.org',
        ];

        $targetDetails = [
            InstanceHelper::WEBROOT_OPTION => static::$targetInstancePath,
            InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch($branch),
            InstanceHelper::URL_OPTION => 'http://target-test.tiki.org',
            InstanceHelper::NAME_OPTION => 'target-test.tiki.org',
        ];

        if (static::$instanceType == 'ssh') {
            $sourceDetails = array_merge($sourceDetails, $sshOptions);
            $targetDetails = array_merge($targetDetails, $sshOptions);
        }

        $sourceInstanceId = InstanceHelper::create($sourceDetails);
        $targetInstanceId = InstanceHelper::create($targetDetails);

        static::$instanceIds['source'] = $sourceInstanceId;
        static::$instanceIds['target'] = $targetInstanceId;
    }

    public static function tearDownAfterClass()
    {
        foreach (static::$instanceIds as $instanceId) {
            $instance = Instance::getInstance($instanceId);
            $access = $instance->getBestAccess();
            $access->shellExec('rm -rf ' . $instance->webroot);
            $instance->delete();
        }

        $fs = new Filesystem();
        $fs->remove(static::$instancePath);
    }

    public function testCloneUpgradeInstance()
    {
        $vcs = strtoupper($_ENV['DEFAULT_VCS']);
        $upgradeBranch = $vcs === 'SRC' ? $_ENV['LATEST_SRC_RELEASE'] : $_ENV['MASTER_BRANCH'];

        $varPrefix = static::$instanceType == 'local' ? '' : strtoupper(static::$instanceType) . '_';

        $host = getenv($varPrefix . 'DB_HOST'); // DB Root Host
        $user = getenv($varPrefix . 'DB_USER'); // DB Root User
        $pass = getenv($varPrefix . 'DB_PASS'); // DB Root Password

        // Clone command
        $arguments = [
            '--source' => static::$instanceIds['source'],
            '--target' => [static::$instanceIds['target']],
            '--db-host' => $host,
            '--db-user' => $user,
            '--db-pass' => $pass,
            '--branch' => VersionControl::formatBranch($upgradeBranch),
            '--skip-cache-warmup' => true,
            '--direct' => true,
        ];

        $result = InstanceHelper::clone($arguments, true, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] === 0);

        $instance = (new Instance())->getInstance(static::$instanceIds['target']);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $this->assertEquals(VersionControl::formatBranch($upgradeBranch), $resultBranch);

        if (static::$instanceType == 'local') {
            $diffDbFile = Files::compareFiles(static::$dbSourceFile, static::$dbTargetFile);
            $this->assertNotEquals([], $diffDbFile);
        }

        // Just to ensure that the database is not empty, since they might/should be different
        $db = $instance->getDatabaseConfig();
        $numTables = $db->query("SELECT COUNT(*) as num_tables FROM information_schema.tables WHERE table_schema = '{$db->dbname}';");
        $this->assertTrue($numTables > 0);
    }

    public function testCloneSameDatabase()
    {
        $upgradeBranch = strtoupper($_ENV['DEFAULT_VCS']) === 'SRC' ? $_ENV['LATEST_SRC_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        $sourceInstance = Instance::getInstance(static::$instanceIds['source']);
        $targetInstance = Instance::getInstance(static::$instanceIds['target']);

        $this->assertTrue($sourceInstance->getBestAccess()->fileExists(static::$dbSourceFile));
        $sourceConfig = $sourceInstance->getBestAccess()->downloadFile(static::$dbSourceFile);

        $targetInstance->getBestAccess()->deleteFile(static::$dbTargetFile);
        $targetInstance->getBestAccess()->uploadFile($sourceConfig, static::$dbTargetFile);
        unlink($sourceConfig);

        $this->assertTrue($targetInstance->getBestAccess()->fileExists(static::$dbTargetFile));

        $arguments = [
            '--source' => static::$instanceIds['source'],
            '--target' => [static::$instanceIds['target']],
            '--branch' => VersionControl::formatBranch($upgradeBranch),
        ];

        $result = InstanceHelper::clone($arguments, true, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] !== 0);
        $this->assertContains('Database host and name are the same', $result['output']);
    }

    /**
     * @depends testCloneUpgradeInstance
     */
    public function testCloneInstanceTargetDB()
    {
        $upgradeBranch = strtoupper($_ENV['DEFAULT_VCS']) === 'SRC' ? $_ENV['LATEST_SRC_RELEASE'] : $_ENV['MASTER_BRANCH'];

        $targetDBName = substr(md5(random_bytes(5)), 0, 8);

        $varPrefix = static::$instanceType == 'local' ? '' : strtoupper(static::$instanceType) . '_';

        $host = getenv($varPrefix . 'DB_HOST'); // DB Root Host
        $user = getenv($varPrefix . 'DB_USER'); // DB Root User
        $pass = getenv($varPrefix . 'DB_PASS'); // DB Root Password

        // Clone command
        $arguments = [
            '--source' => static::$instanceIds['source'],
            '--target' => [static::$instanceIds['target']],
            '--branch' => VersionControl::formatBranch($upgradeBranch),
            '--direct' => null, // also test direct (rsync source/target)
            '--db-host' => $host,
            '--db-user' => $user,
            '--db-pass' => $pass,
            '--db-prefix' => $targetDBName,
            '--skip-cache-warmup' => true,
        ];

        $result = InstanceHelper::clone($arguments, true, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] === 0);

        $instance = (new Instance())->getInstance(static::$instanceIds['target']);
        $dbCnf = $instance->getDatabaseConfig();

        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();
        $this->assertEquals(VersionControl::formatBranch($upgradeBranch), $resultBranch);

        if (static::$instanceType == 'local') {
            $diffDbFile = Files::compareFiles(static::$dbSourceFile, static::$dbTargetFile);
            $this->assertNotEquals([], $diffDbFile);
        }

        static::assertEquals($arguments['--db-host'], $dbCnf->host);
        static::assertContains($arguments['--db-prefix'], $dbCnf->user);
        static::assertContains($arguments['--db-prefix'], $dbCnf->dbname);
    }
}
