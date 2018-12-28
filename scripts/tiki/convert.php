<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

use TikiManager\Access\ShellPrompt;
use TikiManager\Application\Instance;

include_once dirname(__FILE__) . '/../../src/env_setup.php';

$instances = [];
$raw = Instance::getUpdatableInstances();
foreach ($raw as $instance) {
    if ($instance->getLatestVersion()->type == 'cvs') {
        $instances[] = $instance;
    }
}

warning('Make sure you have a recent backup before performing this operation. ' .
    'Some customizations and data may be lost.  Only instances running ' .
    'Tiki can be converted.');

$selection = selectInstances(
    $instances,
    "Which instances do you want to convert from CVS 1.9 to SVN 2.0?\n"
);
$selection = getEntries($instances, $selection);

foreach ($selection as $instance) {
    $access = $instance->getBestAccess('scripting');

    if (! $ok = $access instanceof ShellPrompt) {
        warning("Skipping instance {$instance->name}. Shell access required.");
        continue;
    }

    $locked = $instance->lock();

    $toKeep = [
        'db/local.php',
        'img/wiki',
        'img/wiki_up',
    ];

    $store = [];
    $restore = [];
    foreach ($toKeep as $filename) {
        $file = escapeshellarg($instance->getWebPath($filename));
        $temp = escapeshellarg($instance->getWorkPath(md5($instance->name . $filename)));

        $store[] = "mv $file $temp";
        $restore[] = "cp -R $temp $file";
        $restore[] = "rm -Rf $temp";
    }

    info('Copying user data on drive...');

    $access->shellExec($store);

    $maint = escapeshellarg($instance->getWebPath('maintenance.php'));
    $temp = escapeshellarg($instance->getWorkPath('maintenance.php'));

    info('Removing 1.9 files');

    $access->shellExec(
        "mv $maint $temp",
        "rm -Rf " . escapeshellarg($instance->webroot) . '/*',
        "mv $temp $maint"
    );

    info('Obtaining 2.0 sources');

    $app = $instance->getApplication();
    $app->install(Version::buildFake('svn', 'branches/2.0'));

    info('Restoring user data on drive');

    $access->shellExec($restore);

    $filesToResolve = $app->performUpdate($instance);

    if ($locked) {
        $instance->unlock();
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
