<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    $message = 'ERROR:' . PHP_EOL . 'Cannot locate autoloader file. Please run "composer install".';
    print(PHP_EOL . $message . PHP_EOL . PHP_EOL);
    exit(-1);
}

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use TikiManager\Config\Environment;

$environment = new Environment(__DIR__);
$environment->load();

$application = new Application();

$application->add(new \TikiManager\Command\CreateInstanceCommand());
$application->add(new \TikiManager\Command\AccessInstanceCommand());
$application->add(new \TikiManager\Command\DeleteInstanceCommand());
$application->add(new \TikiManager\Command\EnableWebManagerCommand());
$application->add(new \TikiManager\Command\CopySshKeyCommand());
$application->add(new \TikiManager\Command\WatchInstanceCommand());
$application->add(new \TikiManager\Command\DetectInstanceCommand());
$application->add(new \TikiManager\Command\EditInstanceCommand());
$application->add(new \TikiManager\Command\BlankInstanceCommand());
$application->add(new \TikiManager\Command\CheckInstanceCommand());
$application->add(new \TikiManager\Command\VerifyInstanceCommand());
$application->add(new \TikiManager\Command\UpdateInstanceCommand());
$application->add(new \TikiManager\Command\UpgradeInstanceCommand());
$application->add(new \TikiManager\Command\RestoreInstanceCommand());
$application->add(new \TikiManager\Command\CloneInstanceCommand());
$application->add(new \TikiManager\Command\CloneAndUpgradeInstanceCommand());
$application->add(new \TikiManager\Command\BackupInstanceCommand());
$application->add(new \TikiManager\Command\DeleteBackupCommand());
$application->add(new \TikiManager\Command\FixPermissionsInstanceCommand());
$application->add(new \TikiManager\Command\ListInstanceCommand());
$application->add(new \TikiManager\Command\MaintenanceInstanceCommand());
$application->add(new \TikiManager\Command\ConsoleInstanceCommand());
$application->add(new \TikiManager\Command\StatsInstanceCommand());

$application->add(new \TikiManager\Command\ApplyProfileCommand());

$application->add(new \TikiManager\Command\ManagerInfoCommand());
$application->add(new \TikiManager\Command\CheckRequirementsCommand());
$application->add(new \TikiManager\Command\ResetManagerCommand());
$application->add(new \TikiManager\Command\ReportManagerCommand());
$application->add(new \TikiManager\Command\SetupUpdateCommand());
$application->add(new \TikiManager\Command\SetupWatchManagerCommand());
$application->add(new \TikiManager\Command\SetupBackupManagerCommand());

$application->add(new \TikiManager\Command\ViewDatabaseCommand());
$application->add(new \TikiManager\Command\DeleteDatabaseCommand());

$application->add(new \TikiManager\Command\ClearCacheCommand());

$application->add(new \TikiManager\Command\ClearLogsCommand());

$application->add(new \TikiManager\Command\TikiVersionCommand());

$application->run();
