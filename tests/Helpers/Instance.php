<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Helpers;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TikiManager\Command\CreateInstanceCommand;

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

    public static function create($config = [], $blank = false)
    {
        $defaults = [
            self::TYPE_OPTION => 'local',
            self::URL_OPTION => 'http://managertest.tiki.org',
            self::NAME_OPTION => 'managertest.tiki.org',
            self::EMAIL_OPTION => 'dummy@example.com',
            self::WEBROOT_OPTION => '/tmp/tiki-manager-www', // This value should be overridden
            self::TEMPDIR_OPTION => '/tmp/tiki-manager-tmp', // This value should be overridden
            self::BRANCH_OPTION => VersionControl::formatBranch('trunk'),
            self::BACKUP_USER_OPTION => 'root',
            self::BACKUP_GROUP_OPTION => 'root',
            self::BACKUP_PERMISSION_OPTION => '750',
            self::DB_HOST_OPTION => $_ENV['DB_HOST'],
            self::DB_USER_OPTION => $_ENV['DB_USER'],
            self::DB_PASS_OPTION => $_ENV['DB_PASS'],
            self::DB_PREFIX_OPTION => substr(md5(random_bytes(5)), 0, 8)
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

        $application = new Application();
        $application->add(new CreateInstanceCommand());
        $command = $application->find('instance:create');
        $commandTester = new CommandTester($command);

        $commandTester->execute(
            array_merge([
                'command' => $command->getName()
            ], $settings)
        );

        if ($commandTester->getStatusCode() === 0) {
            return self::getLastInstanceId();
        }

        return false;
    }

    private static function getLastInstanceId()
    {
        $result = query('SELECT instance_id FROM instance ORDER BY instance_id DESC LIMIT 1');
        return (int)$result->fetchColumn();
    }
}
