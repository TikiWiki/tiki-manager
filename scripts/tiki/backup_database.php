<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
    $_SERVER['argv'] = $_GET;
}

$root = $_SERVER['argv'][1];
$outputFile = $_SERVER['argv'][2];
$db_config = "{$root}/db/local.php";

if (!file_exists($db_config)) {
    file_put_contents('php://stderr', "File does not exist: '{$db_config}'");
    exit(1);
}

include "{$db_config}";

$args = array();
if ($user_tiki) {
    $args[] = '-u' . escapeshellarg($user_tiki);
}
if ($pass_tiki) {
    $args[] = '-p' . escapeshellarg($pass_tiki);
}

if ($host_tiki) {
    $parts = explode(';', $host_tiki);
    $args[] = '-h' . escapeshellarg($parts[0]);

    // Parse the MySQL port from a DSN string
    if (isset($parts[1]) && strpos($parts[1], 'port=') !== false) {
        $port = substr($parts[1], 5);
        $args[] = "-P" . escapeshellarg($port);
    }
}

$dbArgs = implode(' ', $args);

// Find out how many non-InnoDB tables exist in the schema
$command = "mysql $dbArgs -BN -e \"SELECT count(TABLE_NAME) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$dbs_tiki' AND engine <> 'InnoDB'\"";
$numTables = exec($command);

if ($numTables === '0') {
    $args[] = "--single-transaction";
} else {
    $args[] = "--lock-tables";
}

$args[] = $dbs_tiki;

$tempFile = escapeshellarg($outputFile);
$command = "mysql $dbArgs $dbs_tiki -BN -e \"SELECT CONCAT('ALTER DATABASE DEFAULT CHARACTER SET ', default_character_set_name, ' COLLATE ', DEFAULT_COLLATION_NAME, ';') FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()\" > " . $tempFile;
exec($command);

$command = "mysql $dbArgs $dbs_tiki -BN -e \"SELECT default_character_set_name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()\"";
exec($command, $output);
$charset = array_shift($output) ?: 'utf8mb4';
$args[] = "--default-character-set=" . $charset;

$args = implode(' ', $args);
$command = "mysqldump --quick --create-options --extended-insert $args >> " . $tempFile;
exec($command);

chmod($outputFile, 0777);

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
