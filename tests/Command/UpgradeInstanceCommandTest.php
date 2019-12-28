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
use TikiManager\Command\UpgradeInstanceCommand;
use TikiManager\Libs\Helpers\VersionControl;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class UpgradeInstanceCommandTester
 * @group Commands
 * @backupGlobals true
 */
class UpgradeInstanceCommandTester extends TestCase
{
    protected static $instancePath;
    protected static $instancePath1;

    public static function setUpBeforeClass()
    {
        $basePath = $_ENV['TESTS_BASE_FOLDER'];
        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'upgrade']);
        self::$instancePath1 = implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'instance1']);
    }

    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(self::$instancePath);
    }

    public function testUpgradeLocalCloneInstance()
    {
        if (strtoupper($_ENV['DEFAULT_VCS']) === 'SRC') {
            $branch = $_ENV['PREV_SRC_MAJOR_RELEASE'];
            $upgradeBranch = $_ENV['LATEST_SRC_RELEASE'];
        } else {
            $branch = $_ENV['PREV_VERSION_BRANCH'];
            $upgradeBranch = $_ENV['MASTER_BRANCH'];
        }

        $instanceId = InstanceHelper::create([
            '--webroot' => self::$instancePath1,
            '--branch' => VersionControl::formatBranch($branch),
        ]);
        $this->assertNotFalse($instanceId);

        $application = new Application();
        $application->add(new UpgradeInstanceCommand());
        $command = $application->find('instance:upgrade');
        $commandTester = new CommandTester($command);

        $arguments = [
            'command' => $command->getName(),
            '--instances' => $instanceId,
            '--branch' => VersionControl::formatBranch($upgradeBranch),
            '--skip-cache-warmup' => true,
            '--skip-reindex' => true,
        ];

        $commandTester->execute($arguments);

        // So we have the execution output
        echo $commandTester->getDisplay();

        $this->assertEquals(0, $commandTester->getStatusCode());

        $instance = (new Instance())->getInstance($instanceId);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $this->assertEquals(VersionControl::formatBranch($upgradeBranch), $resultBranch);
    }
}
