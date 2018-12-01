<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';

if ($_SERVER['argc'] < 2) {
    die(error('Expecting target email address as parameter.'));
}

$log = '';
$email = $_SERVER['argv'][1];

$instances = Instance::getInstances();
$excluded_option = get_cli_option('exclude');

if (! empty($excluded_option)) {
    $instances_to_exclude = explode(',', get_cli_option('exclude'));

    foreach ($instances as $key => $instance) {
        if (in_array($instance->id, $instances_to_exclude)) {
            unset($instances[$key]);
        }
    }
}

foreach ($instances as $instance) {
    $version = $instance->getLatestVersion();

    if (! $version) {
        continue;
    }

    $versionError = false;
    $versionRevision = $version->revision;
    $tikiRevision = $instance->getRevision();

    if (empty($versionRevision)) {
        $log .= "No revision detected for {$instance->name}\n";
        $versionError = true;
    } elseif ($versionRevision != $tikiRevision) {
        $log .= "Check {$instance->name} version conflict\n";
        $log .= "Expected revision {$versionRevision}, found revision {$tikiRevision} on instance.\n";
        $versionError = true;
    }

    if ($versionError) {
        $log .= "Fix this error with TRIM by running \"make check\" and choose instance \"{$instance->id}\".";
        $log .= "\n\n";

        continue;
    }

    if ($version->hasChecksums()) {
        $result = $version->performCheck($instance);

        if (count($result['new']) || count($result['mod']) || count($result['del'])) {
            $log .= "{$instance->name} ({$instance->weburl})\n";

            foreach ($result['new'] as $file => $hash) {
                $log .= "+ $file\n";
            }
            foreach ($result['mod'] as $file => $hash) {
                $log .= "o $file\n";
            }
            foreach ($result['del'] as $file => $hash) {
                $log .= "- $file\n";
            }

            $log .= "\n\n";
        }
    }
}

if (! empty($log)) {
    mail($email, "[TRIM] Potential intrusions detected.", $log);
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
