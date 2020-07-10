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

    public static function setUpBeforeClass()
    {
        if (strtoupper($_ENV['DEFAULT_VCS']) === 'SRC') {
            $prevVersionBranch = $_ENV['PREV_SRC_MAJOR_RELEASE'];
            $branch = $_ENV['LATEST_SRC_RELEASE'];
        } else {
            $prevVersionBranch = $_ENV['PREV_VERSION_BRANCH'];
            $branch = $_ENV['MASTER_BRANCH'];
        }

        $basePath = $_ENV['TESTS_BASE_FOLDER'] . '/clone';

        self::$sourceInstancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'source']);
        self::$targetInstancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'target']);
        self::$dbLocalFile1 = implode(DIRECTORY_SEPARATOR, [self::$sourceInstancePath, 'db', 'local.php']);
        self::$dbLocalFile2 = implode(DIRECTORY_SEPARATOR, [self::$targetInstancePath, 'db', 'local.php']);

        $sourceDetails = [
            InstanceHelper::WEBROOT_OPTION => self::$sourceInstancePath,
            InstanceHelper::BRANCH_OPTION => VersionControl::formatBranch($prevVersionBranch),
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
    }

    public function testLocalCloneInstance()
    {
        $prevVersionBranch = strtoupper($_ENV['DEFAULT_VCS']) === 'SRC' ? $_ENV['PREV_SRC_MAJOR_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        $arguments = [
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--skip-cache-warmup' => true,
        ];

        // Clone command
        $result = InstanceHelper::clone($arguments);
        $this->assertTrue($result);

        $instance = (new Instance())->getInstance(self::$instanceIds['target']);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $diffDbFile = Files::compareFiles(self::$dbLocalFile1, self::$dbLocalFile2);

        $this->assertEquals(VersionControl::formatBranch($prevVersionBranch), $resultBranch);
        $this->assertNotEquals([], $diffDbFile);
    }

    public function testCloneSameDatabase()
    {
        $this->assertFileExists(self::$dbLocalFile1);
        $fileSystem = new Filesystem();
        $fileSystem->copy(self::$dbLocalFile1, self::$dbLocalFile2, true);

        $this->assertFileExists(self::$dbLocalFile2);
        $diffDbFile = Files::compareFiles(self::$dbLocalFile1, self::$dbLocalFile2);
        $this->assertEquals([], $diffDbFile);

        // Clone command
        $application = new Application();
        $application->add(new CloneInstanceCommand());
        $command = $application->find('instance:clone');
        $commandTester = new CommandTester($command);

        $arguments = [
            'command' => 'instance:clone',
            '--source' => strval(self::$instanceIds['source']),
            '--target' => [strval(self::$instanceIds['target'])],
            '--skip-cache-warmup' => true,
        ];

        $commandTester->execute($arguments);

        $output = $commandTester->getDisplay();

        $this->assertContains('Database host and name are the same', $output);
    }
}
