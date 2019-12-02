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
use TikiManager\Tests\Helpers\Files;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;
use TikiManager\Tests\Helpers\VersionControl;

/**
 * Class CloneInstanceCommandTester
 * @group Commands
 * @backupGlobals true
 */
class CloneAndUpgradeCommandTester extends TestCase
{
    protected static $instancePath;
    protected static $tempPath;
    protected static $instancePath1;
    protected static $instancePath2;
    protected static $dbLocalFile1;
    protected static $dbLocalFile2;
    protected static $instanceIds = [];

    public static function setUpBeforeClass()
    {
        $basePath = $_ENV['TESTS_BASE_FOLDER'];

        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'cloneupgrade']);
        self::$tempPath = implode(DIRECTORY_SEPARATOR, [$basePath, 'manager']);
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
        $count = 1;
        $ListCommandInput = [
            [
                '--webroot' => self::$instancePath1,
                '--tempdir' => self::$tempPath,
                '--branch' => VersionControl::formatBranch('branches/20.x'),
            ],
            [
                '--webroot' => self::$instancePath2,
                '--tempdir' => self::$tempPath,
                '--branch' => VersionControl::formatBranch('branches/20.x'),
            ]
        ];

        foreach ($ListCommandInput as $commandInput) {
            $instanceId = InstanceHelper::create($commandInput);
            $this->assertNotFalse($instanceId);
            self::$instanceIds[$count] = $instanceId;
            $count++;
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
            '--branch' => VersionControl::formatBranch('trunk'),
            '--direct' => null // also test direct (rsync source/target)
        ]);

        $instance = new Instance;
        $instance = $instance->getInstance(self::$instanceIds[2]);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $diffDbFile = Files::compareFiles(self::$dbLocalFile1, self::$dbLocalFile2);

        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(VersionControl::formatBranch('trunk'), $resultBranch);
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
        $application->add(new CloneAndUpgradeInstanceCommand());
        $command = $application->find('instance:cloneandupgrade');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command' => $command->getName(),
            '--source' => self::$instanceIds[1],
            '--target' => [self::$instanceIds[2]],
            '--branch' => VersionControl::formatBranch('trunk'),
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('Database host and name are the same', $output);
    }
}
