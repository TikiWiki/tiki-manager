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
use TikiManager\Command\UpdateInstanceCommand;
use TikiManager\Config\App;
use TikiManager\Hooks\TikiCommandHook;
use TikiManager\Libs\Helpers\VersionControl;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class UpdateInstanceCommandTest
 * @group Commands
 * @backupGlobals true
 */
class UpdateInstanceCommandTest extends TestCase
{
    protected static $instanceType;
    protected static $instancePath;
    protected static $dbLocalFile;
    protected static $instanceSettings;
    protected static $instanceIds;

    public static function setUpBeforeClass(): void
    {
        static::$instanceType = getenv('TEST_INSTANCE_TYPE') ?: 'local';
        $basePath = $_ENV['TESTS_BASE_FOLDER'] . '/update';

        self::$instancePath = implode(DIRECTORY_SEPARATOR, [$basePath, 'instance']);
        self::$dbLocalFile =  implode(DIRECTORY_SEPARATOR, [self::$instancePath, 'db', 'local.php']);

        $vcs = strtoupper($_ENV['DEFAULT_VCS']);
        $branch = $vcs === 'SRC' ? $_ENV['PREV_SRC_MAJOR_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

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
        $fs->remove($_ENV['TESTS_BASE_FOLDER'] . '/update');
    }

    public function testUpdateInstance()
    {
        $isSrc = strtoupper($_ENV['DEFAULT_VCS']) === 'SRC';
        $updateBranch = $isSrc ? $_ENV['PREV_SRC_MINOR_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        $instanceId = static::$instanceIds['instance'];

        $command = new UpdateInstanceCommand();

        $arguments = [
            '--instances' => $instanceId,
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

        $this->assertEquals(VersionControl::formatBranch($updateBranch), $resultBranch);

        $hookHandler = App::get('HookHandler');
        $hook = $hookHandler->getHook('instance:create');

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
