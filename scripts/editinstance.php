<?php
// Copyright (c) 2017, Avan.Tech, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';
include_once dirname(__FILE__) . '/../src/dbsetup.php';

$instances = Instance::getInstances();

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

    $backup_user = promptUser('Backup owner', $instance->getProp('backup_user'));
    $backup_group = promptUser('Backup group', $instance->getProp('backup_group'));
    $backup_perm = intval($instance->getProp('backup_perm') ?: 0775);
    $backup_perm = promptUser('Backup file permissions', decoct($backup_perm));

    $instance->name = $host;
    $instance->contact = $contact;
    $instance->webroot = rtrim($webroot, '/');
    $instance->weburl = rtrim($weburl, '/');
    $instance->tempdir = rtrim($tempdir, '/');
    $instance->backup_user = $backup_user;
    $instance->backup_group = $backup_group;
    $instance->backup_perm = octdec($backup_perm);

    $instance->update();
    echo color("Instance information saved.\n", 'green');
}
