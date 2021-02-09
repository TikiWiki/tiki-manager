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
    protected static $instancePath;
    protected static $sourceInstancePath;
    protected static $targetInstancePath;
    protected static $dbLocalFile1;
    protected static $dbLocalFile2;
    protected static $instanceIds = [];

    public static function setUpBeforeClass()
    {
        $basePath = $_ENV['TESTS_BASE_FOLDER'];

        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'cloneupgrade']);
        self::$sourceInstancePath = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'source']);
        self::$targetInstancePath = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'target']);
        self::$dbLocalFile1 = implode(DIRECTORY_SEPARATOR, [self::$sourceInstancePath, 'db', 'local.php']);
        self::$dbLocalFile2 = implode(DIRECTORY_SEPARATOR, [self::$targetInstancePath, 'db', 'local.php']);

        $vcs = strtoupper($_ENV['DEFAULT_VCS']);
        $branch = $vcs === 'SRC' ? $_ENV['PREV_SRC_MAJOR_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        $sourceDetails = [
            InstanceHelper::WEBROOT_OPTION => self::$sourceInstancePath,
            InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch($branch),
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
        $vcs = strtoupper($_ENV['DEFAULT_VCS']);
        $upgradeBranch = $vcs === 'SRC' ? $_ENV['LATEST_SRC_RELEASE'] : $_ENV['MASTER_BRANCH'];

        // Clone command
        $arguments = [
            '--source' => self::$instanceIds['source'],
            '--target' => [self::$instanceIds['target']],
            '--branch' => VersionControl::formatBranch($upgradeBranch),
            '--skip-cache-warmup' => true,
        ];

        if ($direct) {
            $arguments['--direct'] = true;
        }

        $result = InstanceHelper::clone($arguments, true, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] === 0);

        $instance = (new Instance())->getInstance(self::$instanceIds['target']);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $diffDbFile = Files::compareFiles(self::$dbLocalFile1, self::$dbLocalFile2);

        $this->assertEquals(VersionControl::formatBranch($upgradeBranch), $resultBranch);
        $this->assertNotEquals([], $diffDbFile);

        // Just to ensure that the database is not empty, since they might/should be different
        $db = $instance->getDatabaseConfig();
        $numTables = $db->query("SELECT COUNT(*) as num_tables FROM information_schema.tables WHERE table_schema = '{$db->dbname}';");
        $this->assertTrue($numTables > 0);
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
        $upgradeBranch = strtoupper($_ENV['DEFAULT_VCS']) === 'SRC' ? $_ENV['LATEST_SRC_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        $fileSystem = new Filesystem();
        if ($fileSystem->exists(self::$dbLocalFile1)) {
            $fileSystem->copy(self::$dbLocalFile1, self::$dbLocalFile2, true);
        }

        $arguments = [
            '--source' => self::$instanceIds['source'],
            '--target' => [self::$instanceIds['target']],
            '--branch' => VersionControl::formatBranch($upgradeBranch),
        ];

        $result = InstanceHelper::clone($arguments, true, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] !== 0);
        $this->assertContains('Database host and name are the same', $result['output']);
    }

    /**
     * @depends testLocalCloneInstance
     */
    public function testCloneInstanceTargetDB()
    {
        if (strtoupper($_ENV['DEFAULT_VCS']) === 'SRC') {
            $upgradeBranch = $_ENV['LATEST_SRC_RELEASE'];
        } else {
            $upgradeBranch = $_ENV['MASTER_BRANCH'];
        }

        $targetDBName = substr(md5(random_bytes(5)), 0, 8);

        // Clone command
        $arguments = [
            '--source' => self::$instanceIds['source'],
            '--target' => [self::$instanceIds['target']],
            '--branch' => VersionControl::formatBranch($upgradeBranch),
            '--direct' => null, // also test direct (rsync source/target)
            '--db-host' => $_ENV['DB_HOST'],
            '--db-user' => $_ENV['DB_USER'],
            '--db-pass' => $_ENV['DB_PASS'],
            '--db-prefix' => $targetDBName,
            '--skip-cache-warmup' => true,
        ];

        $result = InstanceHelper::clone($arguments, true, ['interactive' => false]);
        $this->assertTrue($result['exitCode'] === 0);

        $instance = (new Instance())->getInstance(self::$instanceIds['target']);
        $dbCnf = $instance->getDatabaseConfig();

        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $diffDbFile = Files::compareFiles(self::$dbLocalFile1, self::$dbLocalFile2);

        $this->assertEquals(VersionControl::formatBranch($upgradeBranch), $resultBranch);
        $this->assertNotEquals([], $diffDbFile);

        self::assertEquals($arguments['--db-host'], $dbCnf->host);
        self::assertContains($arguments['--db-prefix'], $dbCnf->user);
        self::assertContains($arguments['--db-prefix'], $dbCnf->dbname);
    }

}
