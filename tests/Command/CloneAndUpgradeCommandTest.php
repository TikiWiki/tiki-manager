<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Application\Instance;
use TikiManager\Command\CloneAndUpgradeInstanceCommand;
use TikiManager\Command\CloneInstanceCommand;
use TikiManager\Libs\Helpers\VersionControl;
use TikiManager\Tests\Helpers\Files;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class CloneInstanceCommandTester
 * @group Commands
 * @backupGlobals true
 */
class CloneAndUpgradeCommandTester extends TestCase
{
    protected static $instancePath;
    protected static $instancePath1;
    protected static $instancePath2;
    protected static $dbLocalFile1;
    protected static $dbLocalFile2;
    protected static $instanceIds = [];

    public static function setUpBeforeClass()
    {
        $basePath = $_ENV['TESTS_BASE_FOLDER'];

        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'cloneupgrade']);
        self::$instancePath1 = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'instance1']);
        self::$instancePath2 = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'instance2']);
        self::$dbLocalFile1 = implode(DIRECTORY_SEPARATOR, [self::$instancePath1, 'db', 'local.php']);
        self::$dbLocalFile2 = implode(DIRECTORY_SEPARATOR, [self::$instancePath2, 'db', 'local.php']);
    }

    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(self::$instancePath);
    }

    public function testLocalCloneInstance()
    {
        if (strtoupper($_ENV['DEFAULT_VCS']) === 'SRC') {
            $branch = $_ENV['PREV_SRC_MAJOR_RELEASE'];
            $upgradeBranch = $_ENV['LATEST_SRC_RELEASE'];
        } else {
            $branch = $_ENV['PREV_VERSION_BRANCH'];
            $upgradeBranch = $_ENV['MASTER_BRANCH'];
        }

        $count = 1;
        $ListCommandInput = [
            [
                '--webroot' => self::$instancePath1,
                '--branch' => VersionControl::formatBranch($branch),
            ],
            [
                '--webroot' => self::$instancePath2,
                '--branch' => VersionControl::formatBranch($branch),
            ]
        ];

        foreach ($ListCommandInput as $commandInput) {
            $instanceId = InstanceHelper::create($commandInput);
            $this->assertNotFalse($instanceId);
            self::$instanceIds[$count] = $instanceId;
            $count++;
        }

        // Clone command
        $arguments = [
            '--source' => self::$instanceIds[1],
            '--target' => [self::$instanceIds[2]],
            '--branch' => VersionControl::formatBranch($upgradeBranch),
            '--direct' => null, // also test direct (rsync source/target)
            '--skip-cache-warmup' => true,
        ];

        $result = InstanceHelper::clone($arguments, true);
        $this->assertTrue($result);

        $instance = (new Instance())->getInstance(self::$instanceIds[2]);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $diffDbFile = Files::compareFiles(self::$dbLocalFile1, self::$dbLocalFile2);

        $this->assertEquals(VersionControl::formatBranch($upgradeBranch), $resultBranch);
        $this->assertNotEquals([], $diffDbFile);
    }

    /**
     * @depends testLocalCloneInstance
     */
    public function testCloneSameDatabase()
    {
        $upgradeBranch = strtoupper($_ENV['DEFAULT_VCS']) === 'SRC' ? $_ENV['LATEST_SRC_RELEASE'] : $_ENV['MASTER_BRANCH'];

        $fileSystem = new Filesystem();
        if ($fileSystem->exists(self::$dbLocalFile1)) {
            $fileSystem->copy(self::$dbLocalFile1, self::$dbLocalFile2, true);
        }

        // Clone command
        $application = new Application();
        $application->add(new CloneInstanceCommand());
        $application->add(new CloneAndUpgradeInstanceCommand());
        $command = $application->find('instance:cloneandupgrade');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command' => $command->getName(),
            '--source' => self::$instanceIds[1],
            '--target' => [self::$instanceIds[2]],
            '--branch' => VersionControl::formatBranch($upgradeBranch),
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('Database host and name are the same', $output);
    }
}
