<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Helpers;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;
use TikiManager\Command\CreateInstanceCommand;
use TikiManager\Command\CloneAndUpgradeInstanceCommand;
use TikiManager\Command\CloneInstanceCommand;
use TikiManager\Config\App;
use TikiManager\Config\Environment;
use TikiManager\Libs\Helpers\VersionControl;

class Instance
{

    const TYPE_OPTION = '--type';
    const URL_OPTION = '--url';
    const NAME_OPTION = '--name';
    const EMAIL_OPTION = '--email';
    const WEBROOT_OPTION = '--webroot';
    const TEMPDIR_OPTION = '--tempdir';
    const BACKUP_USER_OPTION = '--backup-user';
    const BACKUP_GROUP_OPTION = '--backup-group';
    const BACKUP_PERMISSION_OPTION = '--backup-permission';
    const BRANCH_OPTION = '--branch';
    const DB_HOST_OPTION = '--db-host';
    const DB_USER_OPTION = '--db-user';
    const DB_PASS_OPTION = '--db-pass';
    const DB_PREFIX_OPTION = '--db-prefix';
    const HOST_NAME_OPTION = '--host';
    const HOST_PORT_OPTION = '--port';
    const HOST_USER_OPTION = '--user';
    const HOST_PASS_OPTION = '--pass';

    public static function create($config = [], $blank = false)
    {
        $branch = strtoupper($_ENV['DEFAULT_VCS']) === 'SRC' ? $_ENV['LATEST_SRC_RELEASE'] : $_ENV['PREV_VERSION_BRANCH'];

        $defaults = [
            self::TYPE_OPTION => 'local',
            self::URL_OPTION => 'http://managertest.tiki.org',
            self::NAME_OPTION => 'managertest.tiki.org',
            self::EMAIL_OPTION => 'dummy@example.com',
            self::WEBROOT_OPTION => '/tmp/tiki-manager-www', // This value should be overridden
            self::BRANCH_OPTION => VersionControl::formatBranch($branch),
            self::DB_HOST_OPTION => $_ENV['DB_HOST'],
            self::DB_USER_OPTION => $_ENV['DB_USER'],
            self::DB_PASS_OPTION => $_ENV['DB_PASS'],
            self::DB_PREFIX_OPTION => substr(md5(random_bytes(5)), 0, 8),
        ];

        $settings = array_merge($defaults, $config);

        if ($blank) {
            unset($settings[self::BRANCH_OPTION]);
            unset($settings[self::DB_HOST_OPTION]);
            unset($settings[self::DB_USER_OPTION]);
            unset($settings[self::DB_PASS_OPTION]);
            unset($settings[self::DB_PREFIX_OPTION]);
            $settings['--blank'] = null;
        }

        $command = new CreateInstanceCommand();

        $input = new ArrayInput($settings, $command->getDefinition());
        $input->setInteractive(false);

        try {
            $result = $command->run($input, App::get('output'));

            // If command fails the output is an error code
            if (!empty($result)) {
                return false;
            }

            return self::getLastInstanceId();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param array $arguments
     * @param bool $upgrade
     * @param array $options
     */
    public static function clone(array $arguments, bool $upgrade = false): bool
    {
        $application = new Application();
        $application->add(new CloneInstanceCommand());
        $application->add(new CloneAndUpgradeInstanceCommand());

        $commandName = $upgrade ? 'instance:cloneandupgrade' : 'instance:clone';
        $command = $application->find($commandName);
        $commandTester = new CommandTester($command);

        $arguments = array_merge(['command' => $command->getName()], $arguments);
        $commandTester->execute($arguments, ['interactive' => false]);

        return $commandTester->getStatusCode();
    }

    private static function getLastInstanceId()
    {
        $result = query('SELECT instance_id FROM instance ORDER BY instance_id DESC LIMIT 1');
        return (int)$result->fetchColumn();
    }

    public static function getRandomDbName()
    {
        return substr(md5(random_bytes(5)), 0, 8) . '_db';
    }
}
