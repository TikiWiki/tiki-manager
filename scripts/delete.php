<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';
include_once dirname(__FILE__) . '/../src/check.php';

$args = $_SERVER['argv'];
$selection = array();

if (count($args) === 2 && $args[1] === 'switch') {
    define('ARG_SWITCH', true);
}
else {
    $args = array_filter($args, 'is_numeric');
    $selection = array_map(array('Instance', 'getInstance'), $args);
    $selection = array_filter($selection, 'is_object');
}

if(empty($selection)) {
    echo color("\nWhich instances do you want to remove? " .
        "(This will NOT delete the software itself, " .
        "just your instance connection to it.)\n\n", 'yellow');

    $instances = Instance::getInstances();
    $selection = selectInstances($instances, '');
}

foreach ($selection as $instance) $instance->delete();

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
