#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/vendor/autoload.php';

include_once __DIR__.'/src/env_setup.php';
include_once __DIR__.'/src/check.php';
include_once __DIR__.'/src/dbsetup.php';
include_once __DIR__.'/src/clean.php';

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \App\Command\CreateInstanceCommand());
$application->add(new \App\Command\AccessInstanceCommand());
$application->add(new \App\Command\DeleteInstanceCommand());
$application->add(new \App\Command\EnableWwwInstanceCommand());
$application->add(new \App\Command\CopySshKeyCommand());
$application->add(new \App\Command\WatchInstanceCommand());
$application->add(new \App\Command\DetectInstanceCommand());
$application->add(new \App\Command\EditInstanceCommand());
$application->add(new \App\Command\BlankInstanceCommand());
$application->add(new \App\Command\CheckInstanceCommand());
$application->add(new \App\Command\VerifyInstanceCommand());
$application->add(new \App\Command\UpdateInstanceCommand());
$application->add(new \App\Command\UpgradeInstanceCommand());

$application->add(new \App\Command\ApplyProfileCommand());

$application->add(new \App\Command\FixPermissionsTikiCommand());

$application->add(new \App\Command\CliTrimCommand());
$application->add(new \App\Command\ReportTrimCommand());

$application->run();
