<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

use TikiManager\Application\Instance;
use TikiManager\Application\Tiki as ApplicationTiki;

include_once dirname(__FILE__) . '/../../src/env_setup.php';
include_once dirname(__FILE__) . '/../../src/check.php';

$all = Instance::getInstances();

$instances = [];
foreach ($all as $instance) {
    if ($instance->getApplication() instanceof ApplicationTiki) {
        $instances[$instance->id] = $instance;
    }
}

info("Note: Only Tiki instances can have permissions fixed.\n");

$selection = selectInstances(
    $instances,
    "Which instances do you want to fix?\n"
);

foreach ($selection as $instance) {
    info("Fixing permissions for {$instance->name}");
    $instance->getApplication()->fixPermissions();
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
