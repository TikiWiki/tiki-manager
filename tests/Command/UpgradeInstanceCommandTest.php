<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Application\Instance;
use TikiManager\Command\UpgradeInstanceCommand;
use TikiManager\Config\App;
use TikiManager\Hooks\TikiCommandHook;
use TikiManager\Libs\Helpers\VersionControl;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class UpgradeInstanceCommandTest
 * @group Commands
 * @backupGlobals true
 */
class UpgradeInstanceCommandTest extends TestCase
{
    protected static $instanceType;
    protected static $instancePath;
    protected static $dbLocalFile;
    protected static $instanceSettings;
    protected static $instanceIds;

    public static function setUpBeforeClass(): void
    {
        static::$instanceType = getenv('TEST_INSTANCE_TYPE') ?: 'local';
        $basePath = $_ENV['TESTS_BASE_FOLDER'] . '/upgrade';

        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'instance']);
        self::$dbLocalFile =  implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'db', 'local.php']);

        $vcs = strtoupper($_ENV['DEFAULT_VCS']);
        $branch = $vcs === 'SRC' ? $_ENV['PREV_SRC_MINOR_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        self::$instanceSettings = [
            'local' => [
                InstanceHelper::WEBROOT_OPTION => self::$instancePath,
                InstanceHelper::BRANCH_OPTION => $branch,
            ],
            'ssh' => [
                InstanceHelper::TYPE_OPTION => 'ssh',
                InstanceHelper::BRANCH_OPTION => $branch,
                InstanceHelper::HOST_NAME_OPTION => $_ENV['SSH_HOST_NAME'],
                InstanceHelper::HOST_PORT_OPTION => $_ENV['SSH_HOST_PORT'] ?: 22,
                InstanceHelper::HOST_USER_OPTION => $_ENV['SSH_HOST_USER'],
                InstanceHelper::HOST_PASS_OPTION => $_ENV['SSH_HOST_PASS'] ?: null,
                InstanceHelper::WEBROOT_OPTION => self::$instancePath,
                InstanceHelper::DB_HOST_OPTION => $_ENV['SSH_DB_HOST'],
                InstanceHelper::DB_USER_OPTION => $_ENV['SSH_DB_USER'],
                InstanceHelper::DB_PASS_OPTION => $_ENV['SSH_DB_PASS'],
            ]
        ];

        self::$instanceIds['instance'] = InstanceHelper::create(self::$instanceSettings[static::$instanceType]);
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
        $fs->remove($_ENV['TESTS_BASE_FOLDER'] . '/upgrade');
    }

    public function testUpgradeInstance()
    {
        $isSrc = strtoupper($_ENV['DEFAULT_VCS']) === 'SRC';
        $upgradeBranch = $isSrc ? $_ENV['LATEST_SRC_RELEASE'] : $_ENV['MASTER_BRANCH'];
        $expectedBranch = VersionControl::formatBranch($upgradeBranch);

        $instanceId = static::$instanceIds['instance'];

        $command = new UpgradeInstanceCommand();

        $arguments = [
            '--instances' => $instanceId,
            '--branch' => $expectedBranch,
            '--skip-cache-warmup' => true,
            '--skip-reindex' => true,
        ];

        $input = new ArrayInput($arguments, $command->getDefinition());
        $input->setInteractive(false);

        $exitCode = $command->run($input, App::get('output'));
        $this->assertEquals(0, $exitCode);

        $instance = Instance::getInstance($instanceId);
        $app = $instance->getApplication();
        $resultBranch = $app->getBranch();

        $this->assertEquals($expectedBranch, $resultBranch);


        $hookHandler = App::get('HookHandler');
        $hook = $hookHandler->getHook('instance:upgrade');

        $this->checkHookVars($hook);
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
            'INSTANCE_PREVIOUS_BRANCH_',
        ];

        $instances = explode(',', $hookVars['INSTANCE_IDS']);
        foreach ($instances as $instanceId) {
            foreach ($expectedVariables as $expectedVariable) {
                $varName = $expectedVariable . $instanceId;
                $this->assertTrue(array_key_exists($varName, $hookVars), 'Expected variable ' . $varName);
            }
        }

        $this->assertEquals($_ENV['PREV_VERSION_BRANCH'], $hookVars['INSTANCE_PREVIOUS_BRANCH_' . $instanceId]);
    }
}
