<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

define('SQL_SELECT_TIKI_PREFS', "SELECT DISTINCT value AS '' FROM tiki_preferences WHERE name LIKE '%use_dir' UNION SELECT att_store_dir FROM tiki_forums;");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
    $_SERVER['argv'] = $_GET;
}

$root = $_SERVER['argv'][1];

$db_config = "{$root}/db/local.php";

if (!file_exists($db_config)) {
    file_put_contents('php://stderr', "File does not exist: '{$db_config}'");
    exit(1);
}

include "{$db_config}";

$args = [];
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
        $args[] = '-P' . escapeshellarg($port);
    }
}

$args[] = $dbs_tiki;

$args = implode(' ', $args);
$command = 'echo ' . SQL_SELECT_TIKI_PREFS . ' | mysql -f ' .$args;

echo shell_exec($command);

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
