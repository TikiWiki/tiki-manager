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

$mode = 'update';
if (ARG_SWITCH) {
	$mode = 'upgrade';
}

if (ARG_AUTO)
    $selection = getEntries($instances, implode(' ', array_slice($_SERVER['argv'], 2 )));
else {
    warning("\nWARNING: Only SVN instances can be " . $mode . "d.\n");
    echo "Which instances do you want to " . $mode . "?\n";

    printInstances($instances);

    $selection = promptUser('>>> ');
    $selection = getEntries($instances, $selection);
}

foreach ($selection as $instance) {
    info("Working on: {$instance->name}");

    $locked = $instance->lock();
    $instance->detectPHP();
    $app = $instance->getApplication();

    if (!$app->isInstalled()) {
        ob_start();
        perform_instance_installation($instance);
        $contents = $string = trim(preg_replace('/\s\s+/', ' ', ob_get_contents()));
        ob_end_clean();

        $matches = array();
        if(preg_match('/(\d+\.|trunk)/', $contents, $matches)) {
            $branch_name = $matches[0];
        }
    }

    $version = $instance->getLatestVersion();
    $branch_name = $version->getBranch();
    $branch_version = $version->getBaseVersion();

    if (ARG_SWITCH) {
        $versions = array();
        $versions_raw = $app->getVersions();
        foreach ($versions_raw as $version) {
            if ($version->type == 'svn')
                $versions[] = $version;
        }

        echo "You are currently running: $branch_name\n";
        echo "Which version do you want to upgrade to?\n";

        $counter = 0;
        $found_incompatibilities = false;
        foreach ($versions as $key => $version) {
            $base_version = $version->getBaseVersion();

            $compatible = 0;
            $compatible |= $base_version >= 13;
            $compatible &= $base_version >= $branch_version;
            $compatible |= $base_version === 'trunk';
            $compatible &= $instance->phpversion > 50500;
            $found_incompatibilities |= !$compatible;

            if ($compatible) {
                $counter++;
                echo "[$key] {$version->type} : {$version->branch}\n";
            }
        }

        if ($counter) {
            printf("We detected PHP release %s\n", $instance->getPHPVersion());
    
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
    } else {
		$app_branch = $app->getBranch();
		if ($app_branch == $branch_name) {
			$filesToResolve = $app->performUpdate($instance);
			$version = $instance->getLatestVersion();
			handleCheckResult($instance, $version, $filesToResolve);
		} else {
			echo color("\nError: Tiki Application branch is different than the one stored in the TRIM db.\n\n", 'red');
		}
    }

    if ($locked) $instance->unlock();
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
