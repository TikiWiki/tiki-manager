<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../src/env_setup.php";
include_once dirname(__FILE__) . "/../src/dbsetup.php";

define('ARG_MODE_CLONE',
    $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'clone');
define('ARG_MODE_CLONE_UPDATE',
    $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'update');
define('ARG_MODE_CLONE_UPGRADE',
    $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'upgrade');
define('ARG_MODE_MIRROR',
    $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'mirror');

if (! ARG_MODE_CLONE && ! ARG_MODE_CLONE_UPDATE &&
    ! ARG_MODE_CLONE_UPGRADE && ! ARG_MODE_MIRROR ) {
    echo color("No mode supplied (clone, update, upgrade, or mirror).\n", 'red');
    exit(1);
}

if (ARG_MODE_MIRROR || ARG_MODE_CLONE_UPGRADE) {
    echo color("Clone-and-update not supported (yet).\n", 'red');
    exit(1);
}

$instances = Instance::getInstances();

if(! isset($_SERVER['argv'][2])) {
    echo color("\nNote: Clone/mirror operations are only available on SSH instances.\n\n", 'yellow');

    $src_selection = selectInstances(
        $instances, "Select the source instance:\n" );
}
else {
    $src_selection = getEntries(
        $instances,
        implode(' ', array_slice($_SERVER['argv'], 2))
    );
}

if (count($src_selection) == 0) exit(1);
if (count($src_selection) > 1) {
    echo color("\nError: Only one source instance is permitted.\n\n", 'red');
    exit(1);
}

if(! isset($_SERVER['argv'][3])) {

    echo "\n";
    $dst_selection = selectInstances(
        $instances, "Select the destination instance(s):\n" );
}
else {
    $dst_selection = getEntries(
        $instances,
        implode(' ', array_slice($_SERVER['argv'], 3))
    );
}

info("Creating snapshot of: {$src_selection[0]->name}");
$archive = $src_selection[0]->backup();

if ($archive === null) {
    echo color("\nError: Snapshot creation failed.\n", 'red');
    exit(1);
}

foreach ($dst_selection as $dst_instance) {
    info("Initiating clone/mirror of {$src_selection[0]->name} to {$dst_instance->name}");
    $dst_instance->restore(
        $src_selection[0]->app, $archive, ARG_MODE_CLONE_UPDATE
    );
}

exit(0);

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
