#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/vendor/autoload.php';

include_once __DIR__.'/src/env_setup.php';
include_once __DIR__.'/src/dbsetup.php';

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \App\Command\CreateInstanceCommand());

$application->run();
