<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';
include_once dirname(__FILE__) . '/../src/dbsetup.php';

/*
 * TODO: Allow defining whether the data, the code or both should be copied. Since there is possibly no safe default which is sure not to go against the user's expectation, there should be a mandatory argument ("code", "data" or "both"). 
 */

$instances = Instance::getInstances();

if (! isset($_SERVER['argv'][2])) {
    echo color("\nNOTE: Clone/mirror operations are only available on Local and SSH instances.\n\n", 'yellow');

    $src_selection = selectInstances(
        $instances, "Select the source instance:\n" );
}
else {
    $src_selection = getEntries(
        $instances,
        implode(' ', array_slice($_SERVER['argv'], 2))
    );
}

$instances_pruned = array();
foreach ($instances as $instance) {
    if ($instance->getId() == $src_selection[0]->getId()) continue;
    $instances_pruned[$instance->getId()] = $instance;
}
$instances = $instances_pruned;

if (count($src_selection) == 0) exit(1);
if (count($src_selection) > 1) {
    echo color("\nError: Only one source instance is permitted.\n\n", 'red');
    exit(1);
}

if (! isset($_SERVER['argv'][3])) {

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
    $dst_instance->restore($src_selection[0]->app, $archive, true);
}

exit(0);

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
