<?php
// Copyright (c) 2017, Avan.Tech, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';
include_once dirname(__FILE__) . '/../src/dbsetup.php';

$all = Instance::getInstances();

$instances = [];
foreach ($all as $instance) {
    if ($instance->getApplication() instanceof Application_Tiki) {
        $instances[$instance->id] = $instance;
    }
}

$selection = selectInstances(
    $instances, "Which instances do you want to edit?\n"
);

foreach ($selection as $instance) {
    info("Editing data for {$instance->name}");

    $host = promptUser("Host name", $instance->name);
    $contact = strtolower(promptUser("Contact email", $instance->contact));
    $webroot = promptUser("Web root", $instance->webroot);
    $weburl = promptUser("Web URL", $instance->weburl);
    $tempdir = promptUser("Working directory", $instance->tempdir);

    $instance->name = $host;
    $instance->contact = $contact;
    $instance->webroot = rtrim($webroot, '/');
    $instance->weburl = rtrim($weburl, '/');
    $instance->tempdir = rtrim($tempdir, '/');

    $instance->update();

    echo color("Instance information saved.\n", 'green');
}
