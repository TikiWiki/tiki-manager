<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__DIR__) . '/src/env_setup.php';
include_once dirname(__DIR__) . '/src/check.php';

$selection = array_slice($_SERVER['argv'], 1);
$selection = array_filter($selection, 'is_numeric');
$selection = array_map('intval', $selection);
$selection = array_filter($selection, 'is_numeric');
$selection = array_map(array('Instance', 'getInstance'), $selection);
$selection = array_filter($selection, 'is_object');

if (empty($selection)) {
    $instances = Instance::getInstances();
    if(INTERACTIVE) {
        echo "\nInstances you can verify:\n";
        $selection = selectInstances($instances,
            "You can select one, multiple, or blank for all.\n");
        if (empty($selection)){
            $selection = $instances;
        }
    } else {
        $selection = $instances;
    }
}

foreach ($selection as $instance) {
    $version = $instance->getLatestVersion();

    if (! $version) {
        echo color("Instance [$selection] ({$instance->name}) " .
            "does not have a registered version. Skip.\n", 'yellow');
        continue;
    }

    info("Checking instance: {$instance->name}");

    if ($version->hasChecksums())
        handleCheckResult($instance, $version, $version->performCheck($instance));
    else {
        $input = '';
        $values = array('current', 'source', 'skip');
        warning('No checksums exist.');
        echo "What do you want to do?\n";
        echo "  current - Use the files currently online for checksum.\n";
        echo "   source - Get checksums from repository (best option).\n";
        echo "     skip - Do nothing.\n";
        $input = promptUser('>>>', 'skip', $values);

        switch ($input) {
        case 'source':
            $version->collectChecksumFromSource($instance);
            handleCheckResult($instance, $version, $version->performCheck($instance));
            break;

        case 'current':
            $version->collectChecksumFromInstance($instance);
            break;

        case 'skip':
            continue;
        }
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
