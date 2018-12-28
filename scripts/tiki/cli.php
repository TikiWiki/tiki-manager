<?php
// Copyright (c) 2017, Avan.Tech, et. al.
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

$selection = selectInstances($instances, "Choose instance: \n");

info("Note: If you write 'help' you can check list of commands\n");
$command = promptUser('Write command to execute');

if ($command == 'help') {
    $command = '';
}


foreach ($selection as $instance) {
    info("Calling command in {$instance->name}");
    $access = $instance->getBestAccess('scripting');
    $access->chdir($instance->webroot);
    $new = $access->shellExec(
        ["{$instance->phpexec} -q -d memory_limit=256M console.php " . $command],
        true
    );
    if ($new) {
        info('Result:');
        echo $new . "\n";
    }
}
