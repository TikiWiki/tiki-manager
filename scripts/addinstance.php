<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';
include_once dirname(__FILE__) . '/../src/dbsetup.php';

define('ARG_BLANK', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'blank');

echo color("\nAnswer the following to add a new TRIM instance.\n\n", 'yellow');

$type = $user = $host = $pass = '';

$type = strtolower(
    promptUser('Connection type', null, array('ftp', 'local', 'ssh'))
);

if ($type != 'local') {
    $host = promptUser('Host name');
    $port = promptUser('Port number', ($type == 'ssh') ? 22 : 21);
    $user = strtolower(promptUser('User'));

    while ($type == 'ftp' && empty($pass)) {
        print "Password : ";
        $pass = getPassword(true); print "\n";
    }

    $d_name = $host;
}
else if ($type == 'local') {
    if (function_exists('posix_getpwuid')) {
        $user = posix_getpwuid(posix_geteuid())['name'];
    } elseif (!empty($_SERVER['USER'])) {
        $user = $_SERVER['USER'];
    } else {
        $user = '';
    }
    $pass = '';
    $host = 'localhost';
    $port = 0;
    $d_name = 'localhost';
}

$name = $contact = $webroot = $tempdir = $weburl = '';

$name = promptUser('Instance name', $d_name);
$contact = strtolower(promptUser('Contact email'));

$instance = new Instance;
$instance->name = $name;
$instance->contact = $contact;
$instance->webroot = rtrim($webroot, '/');
$instance->weburl = rtrim($weburl, '/');
$instance->tempdir = rtrim($tempdir, '/');

if ($type == 'ftp') {
    $webroot = promptUser('Web root', "/home/$user/public_html");
    $weburl = promptUser('Web URL', "http://$host");

    $instance->webroot = rtrim($webroot, '/');
    $instance->weburl = rtrim($weburl, '/');
}

$instance->save();
echo color("Instance information saved.\n", 'green');

$access = $instance->registerAccessMethod($type, $host, $user, $pass, $port);

if (! $access) {
    $instance->delete();
    echo color("Set-up failure. Instance removed.\n", 'red');
}

info('Detecting remote configuration.');
if (! $instance->detectSVN()) { 
    die(color("Subversion not detected on the remote server\n", 'red'));
}

if (! $instance->detectPHP()) {
    die(color("PHP Interpreter could not be found on remote host.\n", 'red'));
}
else {
    if ($instance->phpversion < 50300) {
        die(color("PHP Interpreter version is less than 5.3.\n", 'red'));
    }
}

$d_linux = $instance->detectDistribution();
info("You are running : $d_linux");

switch ($d_linux) {
case "ClearOS":
    $backup_user = @posix_getpwuid(posix_geteuid())['name'];
    $backup_group = 'allusers';
    $backup_perm = 02770;
    $host = preg_replace("/[\\\\\/?%*:|\"<>]+/", '-', $instance->name);
    $d_webroot = ($user == 'root' || $user == 'apache') ?
        "/var/www/virtual/{$host}/html/" : "/home/$user/public_html/";
    break;
default:
    $backup_user = @posix_getpwuid(posix_geteuid())['name'];
    $backup_group = @posix_getgrgid(posix_getegid())['name'];
    $backup_perm = 02750;
    $d_webroot = ($user == 'root' || $user == 'apache') ?
        '/var/www/html/' : "/home/$user/public_html/";
}

if ($type != 'ftp') {
    $webroot = promptUser('Web root', $d_webroot);
    $weburl = promptUser('Web URL', "http://$host");
}
$tempdir = promptUser('Working directory', TRIM_TEMP);

if ($access instanceof ShellPrompt)
    $access->shellExec("mkdir -p $tempdir");
else
    echo die(color("Shell access is required to create the working directory. You will need to create it manually.\n", 'yellow'));

$backup_user = promptUser('Backup owner', $backup_user);
$backup_group = promptUser('Backup group', $backup_group);
$backup_perm = promptUser('Backup file permissions', decoct($backup_perm));

$instance->weburl = rtrim($weburl, '/');
$instance->webroot = rtrim($webroot, '/');
$instance->tempdir = rtrim($tempdir, '/');

$instance->backup_user = trim($backup_user);
$instance->backup_group = trim($backup_group);
$instance->backup_perm = octdec($backup_perm);

$instance->save();
echo color("Instance information saved.\n", 'green');

if (ARG_BLANK) 
    echo color("This is a blank (empty) instance. This is useful to restore a backup later.\n", 'blue');
else {
    perform_instance_installation($instance);
    echo color("Please test your site at {$instance->weburl}\n", 'blue');
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
