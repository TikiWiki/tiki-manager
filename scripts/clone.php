<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';
include_once dirname(__FILE__) . '/../src/dbsetup.php';

define(
    'ARG_MODE_CLONE',
    $_SERVER['argc'] >= 2 && $_SERVER['argv'][1] == 'clone'
);
define(
    'ARG_MODE_CLONE_UPGRADE',
    $_SERVER['argc'] >= 2 && $_SERVER['argv'][1] == 'upgrade'
);

// TODO: This function is mostly a copy of the code found in src/dbsetup.php:
// perform_instance_installation().  It should be called from an app-aware method
// (tiki, wordpress, etc).
function getUpgradeVersion($instance)
{
    $found_incompatibilities = false;
    $instance->detectPHP();

    echo "Which version do you want to upgrade to?\n";

    $app = $instance->getApplication();

    $versions = $app->getVersions();
    foreach ($versions as $key => $version) {
        preg_match('/(\d+\.|trunk)/', $version->branch, $matches);
        if (array_key_exists(0, $matches)) {
            if ((($matches[0] >= 13) || ($matches[0] == 'trunk')) &&
                ($instance->phpversion < 50500)) {
                // Nothing to do, this match is incompatible...
                $found_incompatibilities = true;
            } else {
                echo sprintf(
                    "[%3d] %s : %s\n",
                    $key,
                    $version->type,
                    $version->branch
                );
            }
        }
    }

    echo "[ -1] blank : none\n";

    $matches = [];
    preg_match('/(\d+)(\d{2})(\d{2})$/', $instance->phpversion, $matches);

    if (count($matches) == 4) {
        info(sprintf(
            'We detected PHP release: %d.%d.%d',
            $matches[1],
            $matches[2],
            $matches[3]
        ));
    }

    if ($found_incompatibilities) {
        warning("If some versions are not offered, it's likely because the host " .
            "server doesn't meet the requirements for that version (ex: PHP version is too old)");
    }

    $input = promptUser('>>> ');
    $entries = getEntries($versions, $input);

    return $entries[0];
}

if (! ARG_MODE_CLONE && ! ARG_MODE_CLONE_UPGRADE) {
    echo color("No mode supplied (clone, or upgrade).\n", 'red');
    exit(1);
}

$instances = Instance::getInstances(true);

if (! isset($_SERVER['argv'][2])) {
    echo color("\nNOTE: Clone operations are only available on Local and SSH instances.\n\n", 'yellow');

    $src_selection = selectInstances(
        $instances,
        "Select the source instance:\n"
    );
} else {
    $src_selection = getEntries($instances, $_SERVER['argv'][2]);
}

$instances_pruned = [];
foreach ($instances as $instance) {
    if ($instance->getId() == $src_selection[0]->getId()) {
        continue;
    }
    $instances_pruned[$instance->getId()] = $instance;
}
$instances = $instances_pruned;

if (count($src_selection) == 0) {
    exit(1);
}
if (count($src_selection) > 1) {
    echo color("\nError: Only one source instance is permitted.\n\n", 'red');
    exit(1);
}

if (! isset($_SERVER['argv'][3])) {
    echo "\n";
    $dst_selection = selectInstances(
        $instances,
        "Select the destination instance(s):\n"
    );
} else {
    $dst_selection = getEntries($instances, $_SERVER['argv']);
}

if (ARG_MODE_CLONE_UPGRADE) {
    if (! isset($_SERVER['argv'][4])) {
        $upgrade_version = getUpgradeVersion($src_selection[0]);
    } else {
        $upgrade_version = Version::buildFake('svn', $_SERVER['argv'][4]);
    }
}

info("Creating snapshot of: {$src_selection[0]->name}");
$archive = $src_selection[0]->backup();

if ($archive === null) {
    echo color("\nError: Snapshot creation failed.\n", 'red');
    exit(1);
}

$app = $src_selection[0]->getApplication();

// Check if db credentials are not the same between source and destination
$remoteFile = "{$src_selection[0]->webroot}/db/local.php";
$scrAccess = $src_selection[0]->getBestAccess('scripting');
$srcDb = null;
if ($scrAccess->fileExists($remoteFile)) {
    $sourceDBFile = $scrAccess->downloadFile($remoteFile);
    $srcDb = Database::createFromConfig($src_selection[0], $sourceDBFile);
    unlink($sourceDBFile);
}

foreach ($dst_selection as $dst_instance) {
    $remoteFile = "{$dst_instance->webroot}/db/local.php";
    $dstAccess = $dst_instance->getBestAccess('scripting');
    $dstDb = null;
    if ($scrAccess->fileExists($remoteFile)) {
        $dstDBFile = $dstAccess->downloadFile($remoteFile);
        $dstDb = Database::createFromConfig($dst_instance, $dstDBFile);
        unlink($dstDBFile);
    }

    if (!is_null($srcDb) &&
        !is_null($dstDb) &&
        ($scrAccess->host === $dstAccess->host &&
            $srcDb->host === $dstDb->host &&
            $srcDb->dbname === $dstDb->dbname &&
            $srcDb->user === $dstDb->user
        )) {
        error('Database host and name are the same in the source and destination.');

        $valid = false;

        while (!$valid) {
            $dstDb->host = strtolower(promptUser('Database host', $dstDb->host ?: 'localhost'));
            $dstDb->dbname = strtolower(promptUser('Database name', $dstDb->dbname ?: 'tiki_db'));
            $dstDb->user = strtolower(promptUser('Database user', $dstDb->user ?: 'root'));
            print 'Database password: ';
            $dstDb->pass = getPassword(true);
            print "\n";

            $valid = $dstDb->testConnection();
        }

        $tmp = tempnam(TEMP_FOLDER, 'dblocal');

        file_put_contents($tmp, "<?php"          . "\n"
            ."\$db_tiki='{$dstDb->type}';"    . "\n"
            ."\$host_tiki='{$dstDb->host}';"  . "\n"
            ."\$user_tiki='{$dstDb->user}';"  . "\n"
            ."\$pass_tiki='{$dstDb->pass}';"  . "\n"
            ."\$dbs_tiki='{$dstDb->dbname}';" . "\n"
            ."// generated by TRIM " . date('Y-m-d H:i:s +Z'));

        $dstAccess->uploadFile($tmp, 'db/local.php');
    }

    info("Initiating clone of {$src_selection[0]->name} to {$dst_instance->name}");
    $dst_instance->lock();
    $dst_instance->restore($src_selection[0]->app, $archive, true);

    if (ARG_MODE_CLONE_UPGRADE) {
        info("Upgrading to version: {$upgrade_version->branch}");
        $app = $dst_instance->getApplication();
        $app->performUpgrade($dst_instance, $upgrade_version, false);
    }
    $dst_instance->unlock();
}

info("Deleting archive");
$access = $src_selection[0]->getBestAccess('scripting');
$access->shellExec("rm -f " . $archive);

exit(0);

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
