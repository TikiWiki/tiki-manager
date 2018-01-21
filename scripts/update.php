<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';
include_once dirname(__FILE__) . '/../src/check.php';
include_once dirname(__FILE__) . '/../src/dbsetup.php';

define('ARG_SWITCH', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'switch');
define('ARG_AUTO', $_SERVER['argc'] > 2 && $_SERVER['argv'][1] == 'auto');

$instances = Instance::getUpdatableInstances();

if (ARG_AUTO)
    $selection = getEntries($instances, implode(' ', array_slice($_SERVER['argv'], 2 )));
else {
    warning("\nWARNING: Only SVN instances can be updated.\n");
    echo "Which instances do you want to update?\n";

    printInstances($instances);

    $selection = promptUser('>>> ');
    $selection = getEntries($instances, $selection);
}

foreach ($selection as $instance) {
    info("Working on: {$instance->name}");

    $locked = $instance->lock();
    $instance->detectPHP();
    $app = $instance->getApplication();

    ob_start();
    perform_instance_installation($instance);
    $contents = $string = trim(preg_replace('/\s\s+/', ' ', ob_get_contents()));
    ob_end_clean();

    $matches_a = array();
    preg_match('/(\d+\.|trunk)/', $contents, $matches_a);

    if (!is_array($matches_a) || count($matches_a) === 0) {
        $matches_a = array(0);
    }

    if (ARG_SWITCH) {
        $versions = array();
        $versions_raw = $app->getVersions();
        foreach ($versions_raw as $version) {
            if ($version->type == 'svn')
                $versions[] = $version;
        }

        echo "You are currently running: $contents\n";
        echo "Which version do you want to upgrade to?\n";

        $i = 0;
        $found_incompatibilities = false;
        foreach ($versions as $key => $version) {
            preg_match('/(\d+\.|trunk)/', $version->branch, $matches_b);

            if ((($matches_b[0] >= 13) || ($matches_b[0] == 'trunk')) &&
                ($instance->phpversion < 50500) || ($matches_b[0] < $matches_a[0]))
                $found_incompatibilities = true;
            else {
                $i++;
                echo "[$key] {$version->type} : {$version->branch}\n";
            }
        }

        if ($i) {
            $matches_c = array();
            preg_match('/(\d+)(\d{2})(\d{2})$/', $instance->phpversion, $matches_c);

            if (array_key_exists(1, $matches_c) &&
                array_key_exists(2, $matches_c) &&
                array_key_exists(3, $matches_c)) {
                printf("We detected PHP release %d.%d.%d\n",
                    $matches_c[1], $matches_c[2], $matches_c[3]);
            }
    
            if ($found_incompatibilities) {
                warning('WARNING: If some versions are not offered, ' .
                    "it's likely because the host server doesn't meet the requirements " .
                    "for that version (ex: PHP version is too old)");
            }

            $input = promptUser('>>> ');
            $versionSel = getEntries($versions, $input);
            if (empty($versionSel) && ! empty($input))
                $target = Version::buildFake('svn', $input);
            else
                $target = reset($versionSel);

            if (count($versionSel) > 0) {
                $filesToResolve = $app->performUpdate($instance, $target);
                $version = $instance->getLatestVersion();
                handleCheckResult($instance, $version, $filesToResolve);
            }
            else
                warning('No version selected. Nothing to perform.');
        }
        else {
            warning('No upgrades are available. This is likely because you are already at ' .
                'the latest version permitted by the server.'
            );
        }
    }
    else {
        $filesToResolve = $app->performUpdate($instance);
        $version = $instance->getLatestVersion();
        handleCheckResult($instance, $version, $filesToResolve);
    }

    if ($locked) $instance->unlock();
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
