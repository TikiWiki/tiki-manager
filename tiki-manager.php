<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

// Check PHP Version early
if (version_compare(PHP_VERSION, '7.4', '<')) {
    fwrite(STDERR, "Error: Tiki Manager requires PHP 7.4 or later. Detected: PHP " . PHP_VERSION . PHP_EOL);
    exit(1);
}

require_once 'src/Libs/Helpers/functions.php';

try {
    $pharPath = Phar::running(false);
    $isPhar = isset($pharPath) && !empty($pharPath);

    if (!$isPhar && !$composer = detectComposer(__DIR__)) {
        print('Downloading composer.phar...' . PHP_EOL);
        $composer = installComposer(__DIR__);
    }

    if (!$isPhar && !file_exists(__DIR__ . '/vendor/autoload.php')) {
        installComposerDependencies(__DIR__, $composer);
    }
} catch (Exception $e) {
    fwrite(STDERR, "Error occurred in " . $e->getFile() . " on line " . $e->getLine() . ":" . PHP_EOL);
    fwrite(STDERR, "Error Message: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "Stack Trace:\n" . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

use TikiManager\Console;

try {
    $console = new Console();
    $console->init();
    $console->run();
} catch (Throwable $e) {
    fwrite(STDERR, "An unexpected error occurred in " . $e->getFile() . " on line " . $e->getLine() . ":\n");
    fwrite(STDERR, "Error Message: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, "Stack Trace:\n" . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
