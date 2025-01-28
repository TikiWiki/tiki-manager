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
$arguments = $_SERVER['argv'];
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
$numTables = exec($command, $output, $returnCode);
if ($returnCode !== 0) {
    print(implode("\n", $output));
    file_put_contents('php://stderr', "Failed to get the non-innodb table count.");
    exit(1);
}
unset($output);

if ($numTables === '0') {
    $args[] = "--single-transaction";
} else {
    $args[] = "--lock-tables";
}

$args[] = $dbs_tiki;

$tempFile = escapeshellarg($outputFile);
$command = "mysql $dbArgs $dbs_tiki -BN -e \"SELECT CONCAT('ALTER DATABASE DEFAULT CHARACTER SET ', default_character_set_name, ' COLLATE ', DEFAULT_COLLATION_NAME, ';') FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()\" > " . $tempFile;
exec($command, $output, $returnCode);
if ($returnCode !== 0) {
    print(implode("\n", $output));
    file_put_contents('php://stderr', "Failed to insert default character set in dump file.");
    exit(1);
}
unset($output);

$command = "mysql $dbArgs $dbs_tiki -BN -e \"SELECT default_character_set_name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()\"";
exec($command, $output, $returnCode);
if ($returnCode !== 0) {
    print(implode("\n", $output));
    file_put_contents('php://stderr', "Failed to get default character set.");
    exit(1);
}
$charset = array_shift($output) ?: 'utf8mb4';
$args[] = "--default-character-set=" . $charset;
unset($output);

// mysqldump 8 enabled a new flag called column-statistics by default.
// When you have MySQL client above 8 and try to run mysqldump on older MySQL versions, an error occurs
$command = "mysqldump --help | grep -i 'column-statistics.*TRUE' | wc -l";
if (exec($command) == "1") {
    $args[] = "--column-statistics=0";
}

if (!in_array('include-index-backup', $arguments)) {
    $exclude_tables = "mysql $dbArgs -N -B -e \"SELECT table_name FROM information_schema.tables WHERE table_schema = '{$dbs_tiki}' AND table_name LIKE 'index_%'\"";

    exec($exclude_tables, $tablesToIgnore);

    foreach ($tablesToIgnore as $table) {
        $args[] = "--ignore-table=$dbs_tiki.$table";
    }
}

$args = implode(' ', $args);
$command = "(mysqldump --quick --create-options --extended-insert --no-tablespaces $args >> " . $tempFile . ") 2>&1";
exec($command, $output, $returnCode);
if ($returnCode !== 0) {
    print(implode("\n", $output));
    file_put_contents('php://stderr', "Dump failed.");
    exit($returnCode);
}
unset($output);

chmod($outputFile, 0777);

print("DATABASE BACKUP OK");

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
