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
$selection = array_map(['Instance', 'getInstance'], $selection);
$selection = array_filter($selection, 'is_object');

if (empty($selection)) {
    $instances = Instance::getInstances(true);
    if (INTERACTIVE) {
        echo "\nInstances you can verify:\n";
        $selection = selectInstances(
            $instances,
            "You can select one, multiple, or blank for all.\n"
        );
        if (empty($selection)) {
            $selection = $instances;
        }
    } else {
        $selection = $instances;
    }
}

/** @var Instance $instance */
foreach ($selection as $instance) {

    /** @var Version $version */
    $version = $instance->getLatestVersion();

    if (! $version) {
        echo color("Instance [$selection] ({$instance->name}) " .
            "does not have a registered version. Skip.\n", 'yellow');
        continue;
    }

    info("Checking instance: {$instance->name}");

    $versionRevision = $version->revision;
    $tikiRevision = $instance->getRevision();

    if (! empty($versionRevision) && $versionRevision == $tikiRevision && $version->hasChecksums()) {
        handleCheckResult($instance, $version, $version->performCheck($instance));
        continue;
    }

    $fetchChecksum = false;

    if (empty($versionRevision)) {
        warning('No revision detected for instance.');
        $fetchChecksum = true;
    }

    if (!empty($versionRevision) && $versionRevision != $tikiRevision) {
        warning('Revision mismatch between trim version and instance.');
        $fetchChecksum = true;
    }

    if (empty($trimInstanceRevision) || $trimInstanceRevision != $tikiRevision) {
        warning('It is recommended to fetch new checksum information.');
        $fetchChecksum = true;
    }

    if (! $version->hasChecksums()) {
        warning('No checksums exist.');
        $fetchChecksum = true;
    }

    if ($fetchChecksum) {
        // Create a new version
        $version = $instance->createVersion();
        /** @var Application_Tiki $app */
        $app = $instance->getApplication();
        $version->type = $app->getInstallType();
        $version->branch = $app->getBranch();
        $version->date = date('Y-m-d');
        $version->save();

        $input = '';
        $values = ['current', 'source', 'skip'];

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
