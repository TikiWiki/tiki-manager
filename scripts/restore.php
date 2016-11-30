<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';
include_once dirname(__FILE__) . '/../src/dbsetup.php';

define('ARG_SWITCH', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'switch');

$all = array();
$instances = array();
$raw = Instance::getInstances();
foreach ($raw as $instance) {
    $all[$instance->id] = $instance;

    if(! $instance->getApplication())
        $instances[$instance->id] = $instance;
}

warning("\nNOTE: It is only possible to restore a backup on a blank install.");
warning("WARNING: If you are restoring to the same server, this can lead to " .
    "data corruption as both the original and restored Tiki are using the " .
    "same folder for storage.\n");

$selection = selectInstances($instances, "Which instance do you want to restore to?\n");
$restorable = Instance::getRestorableInstances();

foreach ($selection as $instance) {
    info($instance->name);

    echo "Which instance do you want to restore from?\n";

    printInstances($restorable);

    $single = promptUser('>>> ');
    if (! $single = reset(getEntries($restorable, $single))) {
        warning('No instance selected.');
        continue;
    } 

    echo "Which backup do you want to restore?\n";

    $files = $single->getArchives();
    foreach ($files as $key => $path)
        echo "[$key] " . basename($path). "\n";

    $file = promptUser('>>> ');
    if (! $file = reset(getEntries($files, $file))) {
        warning('Skip: No archive file selected.');
        continue;
    }

    $instance->restore($single->app, $file);

    info("It is now time to test your site: {$instance->name}");
    info("If there are issues, connect with make access to troubleshoot directly on the server.");
    info("You'll need to login to this restored instance and update the file paths with the new values.");
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
