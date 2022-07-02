<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

$composerBin = trim(exec('which composer'));
$composerLock = __DIR__ . '/../../composer.lock';

if (empty($composerBin)) {
    echo "composer.phar not found" . PHP_EOL;
    exit(1);
}

if (! file_exists($composerLock)) {
    echo "composer.lock not found" . PHP_EOL;
    exit(1);
}

$initial_md5 = md5_file($composerLock);
printf('Getting md5 from %s ...' . PHP_EOL . 'result: %s' . PHP_EOL, $composerLock, $initial_md5);
printf('Running composer update...' . PHP_EOL);

$output = null;
$exitCode = 0;

exec(sprintf('%s %s update --prefer-dist --working-dir=%s --no-progress --no-interaction', PHP_BINARY, $composerBin, dirname($composerLock)), $output, $exitCode);
if ($exitCode !== 0) {
    echo PHP_EOL . "Error: Failed to upgrade composer dependencies. Aborting." . PHP_EOL . PHP_EOL;
    exit($exitCode);
}

$final_md5 = md5_file($composerLock);
printf('Getting md5 from %s after composer update...' . PHP_EOL . 'result: %s' . PHP_EOL, $composerLock, $final_md5);

if ($initial_md5 !== $final_md5) {
    $jsonContent = json_decode(file_get_contents($composerLock));

    if (! empty($jsonContent->packages)) {
        $errors = [];
        foreach ($jsonContent->packages as $package) {
            if (! empty($package->type) && $package->type === "metapackage") {
                continue; // metapackage is a empty package and does not have dist.url
            }
            if (strrpos($package->dist->url, 'https://composer.tiki.org') !== 0) {
                $errors[] = "Package: " . $package->name . ", dist.url: " . $package->dist->url;
            }
        }

        // Not all packages at the moment are in composer.tiki.org
        // Report but do not exit
        if (count($errors)) {
            echo PHP_EOL;
            foreach ($errors as $error) {
                echo $error . PHP_EOL;
            }
            //echo PHP_EOL . "Error: composer.lock might contain packages from unverified sources. Aborting." . PHP_EOL . PHP_EOL;
            //exit(1);

            echo PHP_EOL . "Warning: composer.lock might contain packages from unverified sources." . PHP_EOL . PHP_EOL;
        }
    }

    printf("Vendor dependencies updated");
} else {
    printf("Nothing to be done...");
}
