<?php

require __DIR__.'/vendor/autoload.php';

include_once __DIR__.'/src/env_setup.php';
include_once __DIR__.'/src/check.php';
include_once __DIR__.'/src/dbsetup.php';
include_once __DIR__.'/src/clean.php';

use Symfony\Component\Console\Application;

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
$application->add(new \TikiManager\Command\FixPermissionsInstanceCommand());

$application->add(new \TikiManager\Command\ApplyProfileCommand());

$application->add(new \TikiManager\Command\ConsoleInstanceCommand());
$application->add(new \TikiManager\Command\ResetManagerCommand());
$application->add(new \TikiManager\Command\ReportManagerCommand());

$application->add(new \TikiManager\Command\ViewDatabaseCommand());
$application->add(new \TikiManager\Command\DeleteDatabaseCommand());

$application->run();
