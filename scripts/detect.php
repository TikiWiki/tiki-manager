<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';
include_once dirname(__FILE__) . '/../src/check.php';
include_once dirname(__FILE__) . '/../src/dbsetup.php';

$instances = Instance::getInstances(true);
$selection = selectInstances($instances,
    "Which instances do you want to detect?\n");

foreach ($selection as $instance) {
    if (! $instance->detectPHP()) {
        if ($instance->phpversion < 50300)
            die(color("PHP Interpreter version is less than 5.3.\n", 'red'));
        else
            die(color("PHP Interpreter could not be found on remote host.\n", 'red'));
    }

    perform_instance_installation($instance);

    $matches = array();
    preg_match('/(\d+)(\d{2})(\d{2})$/',
        $instance->phpversion, $matches);

    if (count($matches) == 4) {
        info(sprintf("Detected PHP : %d.%d.%d",
            $matches[1], $matches[2], $matches[3]));
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
