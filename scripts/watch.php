<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';

if ($_SERVER['argc'] != 2)
    die(error('Expecting target email address as parameter.'));

$log = '';
$email = $_SERVER['argv'][1];

$instances = Instance::getInstances();
foreach ($instances as $instance) {
    $version = $instance->getLatestVersion();

    if (! $version) continue;

    if ($version->hasChecksums()) {
        $result = $version->performCheck($instance);

        if (count($result['new']) || count($result['mod']) || count($result['del'])) {
            $log .= "{$instance->name} ({$instance->weburl})\n";

            foreach ($result['new'] as $file => $hash)
                $log .= "+ $file\n";
            foreach ($result['mod'] as $file => $hash)
                $log .= "o $file\n";
            foreach ($result['del'] as $file => $hash)
                $log .= "- $file\n";

            $log .= "\n\n";
        }
    }
}

if (! empty($log))
    mail($email, "[TRIM] Potential intrusions detected.", $log);

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
