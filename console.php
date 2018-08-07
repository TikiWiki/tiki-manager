#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/vendor/autoload.php';

include_once __DIR__.'/src/env_setup.php';
include_once __DIR__.'/src/dbsetup.php';

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \App\Command\CreateInstanceCommand());
$application->add(new \App\Command\AccessInstanceCommand());
$application->add(new \App\Command\DeleteInstanceCommand());
$application->add(new \App\Command\EnableWwwInstanceCommand());
$application->add(new \App\Command\CopySshKeyCommand());
$application->add(new \App\Command\WatchInstanceCommand());

$application->run();
