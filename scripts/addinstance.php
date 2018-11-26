<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';
include_once dirname(__FILE__) . '/../src/dbsetup.php';

define('ARG_BLANK', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'blank');

echo color("\nAnswer the following to add a new TRIM instance.\n\n", 'yellow');

$instance = new Instance();
$instance->type = strtolower(promptUser('Connection type ', null, explode(',', Instance::TYPES)));

$access = Access::getClassFor($instance->type);
$access = new $access($instance);
$discovery = new \trim\instance\Discovery($instance, $access);

if ($instance->type !== 'local') {
    $access->host = promptUser('Host name');
    $access->user = promptUser('User');
    $access->password = $instance->type == 'ftp' ? promptPassword() : '';
    $port = promptUser('Port number', ($instance->type == 'ssh') ? 22 : 21);
} else {
    $access->host = 'localhost';
    $access->user = $discovery->detectUser();
}

$instance->weburl = promptUser('Web URL', $discovery->detectWeburl());
$instance->name = promptUser('Instance name', $discovery->detectName());
$instance->contact = strtolower(promptUser('Contact email'));

if (!$access->firstConnect()) {
    error('Failed to setup access');
}

$instance->save();
$access->save();
echo color("Instance information saved.\n", 'green');

info("Running on " . $discovery->detectDistro());
if ($access instanceof ShellPrompt) {
    $webroot = promptUser('Web root', $discovery->detectWebroot());
    $testResult = $access->shellExec('test -d ' . escapeshellarg($webroot) .' && echo EXISTS');
    if ($testResult != 'EXISTS') {
        echo "Directory [" . $webroot . "] does not exist.\n";
        $confirmAnswer = promptUser('Create directory?', false, ['yes','no']);
        $createResult = '';
        if ($confirmAnswer == 'yes') {
            $createResult = $access->shellExec('mkdir -m777 -p ' . escapeshellarg($webroot) . ' && echo SUCCESS');
        }

        if ($confirmAnswer != 'yes') {
            echo die(color("Webroot directory not created. Unable to continue, TRIM requires an existing webroot directory.\n", 'yellow'));
        }

        if ($createResult != 'SUCCESS') {
            echo die(color("Unable to create webroot directory. Unable to continue, TRIM requires an existing webroot directory.\n", 'yellow'));
        }
    }

    $instance->webroot = $webroot;

    $instance->tempdir = promptUser('Working directory', TRIM_TEMP);
    $access->shellExec('mkdir -m777 -p ' . escapeshellarg($instance->tempdir));
} else {
    echo die(color("Shell access is required to create the working and web root directory. You will need to create it manually.\n", 'yellow'));
}

list($backup_user, $backup_group, $backup_perm) = $discovery->detectBackupPerm();
$instance->backup_user = promptUser('Backup owner', $backup_user);
$instance->backup_group = promptUser('Backup group', $backup_group);
$instance->backup_perm = intval(promptUser('Backup file permissions', decoct($backup_perm)), 8);

$instance->phpexec = $discovery->detectPHP();
$instance->phpversion = $discovery->detectPHPVersion();

$instance->save();
echo color("Instance information saved.\n", 'green');

if (ARG_BLANK) {
    echo color("This is a blank (empty) instance. This is useful to restore a backup later.\n", 'blue');
} else {
    perform_instance_installation($instance);
    echo color("Please test your site at {$instance->weburl}\n", 'blue');
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
