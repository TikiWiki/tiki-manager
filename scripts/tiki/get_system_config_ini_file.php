<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
    $_SERVER['argv'] = $_GET;
}

$root = $_SERVER['argv'][1];

$dbConfig = "{$root}/db/local.php";

if (!file_exists($dbConfig)) {
    file_put_contents('php://stderr', "File does not exist: '{$dbConfig}'");
    exit(1);
}

include "{$dbConfig}";

$iniFilePath = '';
if (isset($system_configuration_file)) {
    $filename = realpath($system_configuration_file);

    $location = 'local';

    if ($filename === false || strncmp($filename, $root, strlen($root)) !== 0) {
        // file is outside root directory
        $location = 'external';
    }

    $iniFilePath = $system_configuration_file . '||' . $location;
}

echo $iniFilePath;
