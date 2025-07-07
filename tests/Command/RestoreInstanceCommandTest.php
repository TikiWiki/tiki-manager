<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;
use TikiManager\Command\RestoreInstanceCommand;
use TikiManager\Application\Instance;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Config\App;
use TikiManager\Hooks\TikiCommandHook;
use TikiManager\Libs\Database\Database;
use PHPUnit\Framework\TestCase;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class RestoreInstanceCommandTest
 * @group Commands
 * @backupGlobals true
 */
class RestoreInstanceCommandTest extends TestCase
{
    protected static $instanceType;
    private static $instanceBasePath;
    private static $instanceIds = [];
    private static $dbLocalFileInstance1;
    private static $instance1Path;
    private static $dbLocalFileInstance2;
    private static $instance2Path;
    protected static $instanceSettings;

    public static function setUpBeforeClass(): void
    {
        static::$instanceType = getenv('TEST_INSTANCE_TYPE') ?: 'local';
        self::$instanceBasePath = $_ENV['TESTS_BASE_FOLDER'] . '/restore';

        // instances paths
        self::$instance1Path = implode(DIRECTORY_SEPARATOR, [self::$instanceBasePath, 'instance1']);
        self::$instance2Path = implode(DIRECTORY_SEPARATOR, [self::$instanceBasePath, 'instance2']);

        self::$dbLocalFileInstance1 = implode(DIRECTORY_SEPARATOR, [self::$instance1Path, 'db', 'local.php']);
        self::$dbLocalFileInstance2 = implode(DIRECTORY_SEPARATOR, [self::$instance2Path, 'db', 'local.php']);

        self::$instanceSettings = [
            'local' => [
                InstanceHelper::WEBROOT_OPTION => self::$instance1Path,
            ],
            'ssh' => [
                InstanceHelper::TYPE_OPTION => 'ssh',
                InstanceHelper::HOST_NAME_OPTION => $_ENV['SSH_HOST_NAME'],
                InstanceHelper::HOST_PORT_OPTION => $_ENV['SSH_HOST_PORT'] ?: 22,
                InstanceHelper::HOST_USER_OPTION => $_ENV['SSH_HOST_USER'],
                InstanceHelper::HOST_PASS_OPTION => $_ENV['SSH_HOST_PASS'] ?: null,
                InstanceHelper::WEBROOT_OPTION => self::$instance1Path,
                InstanceHelper::DB_HOST_OPTION => $_ENV['SSH_DB_HOST'],
                InstanceHelper::DB_USER_OPTION => $_ENV['SSH_DB_USER'],
                InstanceHelper::DB_PASS_OPTION => $_ENV['SSH_DB_PASS'],
            ]
        ];

        $instanceId = InstanceHelper::create(self::$instanceSettings[static::$instanceType]);

        self::$instanceIds['instance1'] = $instanceId;

        $blankOptions = array_merge(self::$instanceSettings[static::$instanceType], [
            InstanceHelper::NAME_OPTION => 'blank.tiki.org',
            InstanceHelper::WEBROOT_OPTION => self::$instance2Path,
        ]);

        $instanceId = InstanceHelper::create($blankOptions, true);

        self::$instanceIds['instance2'] = $instanceId;

        $instance = Instance::getInstance(self::$instanceIds['instance1']);
        $instance->backup();
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
        $fs->remove(self::$instanceBasePath);
    }

    public function testRestoreInstance()
    {
        $this->assertNotFalse(self::$instanceIds['instance1']);
        $this->assertNotFalse(self::$instanceIds['instance2']);

        $application = new Application();
        $application->add(new RestoreInstanceCommand());

        $command = $application->find('instance:restore');
        $commandTester = new CommandTester($command);

        $inputs = [
            self::$instanceIds['instance2'], // blank instance
            self::$instanceIds['instance1'], // master instance
            0, // first backup in list (last backup)
        ];

        // LOCAL - Database ROOT credentials are passed in $_ENV
        if (static::$instanceType == 'ssh') {
            $inputs[] = $_ENV['SSH_DB_HOST'];
            $inputs[] = $_ENV['SSH_DB_USER'];
            $inputs[] = $_ENV['SSH_DB_PASS'];
        }

        $inputs[] = 'yes'; // DB create database and user
        $inputs[] = substr(md5(random_bytes(5)), 0, 8); // DB prefix

        $commandTester->setInputs($inputs);

        $commandTester->execute([
            'command'  => $command->getName()
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('It is now time to test your site: blank.tiki.org', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        $restoredInstance = Instance::getInstance(self::$instanceIds['instance2']);
        $this->assertTrue($restoredInstance->getBestAccess()->fileExists(self::$dbLocalFileInstance2));

        $hookHandler = App::get('HookHandler');
        $hook = $hookHandler->getHook('instance:restore');

        $this->checkHookVars($hook);
    }

    /**
     * @depends testRestoreInstance
     */
    public function testDiffDataBase()
    {
        //This only works on Local instances
        if (static::$instanceType != 'local') {
            $this->markTestSkipped('Instance types not supported');
        }

        if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
            $this->markTestSkipped('dbdiff tool does not support PHP8.1');
        }

        $sourceInstance = Instance::getInstance(self::$instanceIds['instance1']);
        $restoredInstance = Instance::getInstance(self::$instanceIds['instance2']);

        $this->assertTrue($sourceInstance->getBestAccess()->fileExists(static::$dbLocalFileInstance1));
        $this->assertTrue($restoredInstance->getBestAccess()->fileExists(static::$dbLocalFileInstance2));

        $trunkConfig = Database::getInstanceDataBaseConfig(self::$dbLocalFileInstance1);
        $blankToTrunkConfig = Database::getInstanceDataBaseConfig(self::$dbLocalFileInstance2);

        $host = getenv('DB_HOST'); // DB Host
        $user = getenv('DB_USER'); // DB Root User
        $pass = getenv('DB_PASS'); // DB Root Password
        $port = getenv('DB_PORT') ?? '3306';

        $db1 = $trunkConfig['dbname'];
        $db2 = $blankToTrunkConfig['dbname'];

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

        // For debugging purposes
        echo $output;

        $this->assertStringContainsString('Identical resources', $output);
        $this->assertStringContainsString('Completed', $output);
    }

    private function checkHookVars(TikiCommandHook $hook)
    {
        // Check HOOK variables
        $hookVars = $hook->getPostHookVars();

        $expectedVariables = [
            'INSTANCE_TYPE_',
            'INSTANCE_VCS_TYPE_',
            'INSTANCE_NAME_',
            'INSTANCE_WEBROOT_',
            'INSTANCE_WEBURL_',
            'INSTANCE_TEMPDIR_',
            'INSTANCE_PHPEXEC_',
            'INSTANCE_PHPVERSION_',
            'INSTANCE_BACKUP_USER_',
            'INSTANCE_BACKUP_GROUP_',
            'INSTANCE_BACKUP_PERM_',
            'INSTANCE_BRANCH_',
            'INSTANCE_LAST_ACTION_',
            'INSTANCE_LAST_ACTION_DATE_',
        ];

        $instances = explode(',', $hookVars['INSTANCE_IDS']);
        foreach ($instances as $instanceId) {
            foreach ($expectedVariables as $expectedVariable) {
                $varName = $expectedVariable . $instanceId;
                $this->assertTrue(array_key_exists($varName, $hookVars), 'Expected variable ' . $varName);
            }
        }
    }
}
