<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET')
    $_SERVER['argv'] = $_GET;

$root = $_SERVER['argv'][1];
$outputFile = $_SERVER['argv'][2];

include "{$root}/db/local.php";

$args = array();
if ($user_tiki)
    $args[] = '-u' . escapeshellarg($user_tiki);
if ($pass_tiki)
    $args[] = '-p' . escapeshellarg($pass_tiki);

if ($host_tiki) {
    $parts = explode(';', $host_tiki);
    $args[] = '-h' . escapeshellarg($parts[0]);

    // Parse the MySQL port from a DSN string
    if (isset($parts[1]) && strpos($parts[1], 'port=') !== false) {
        $port = substr($parts[1], 5);
        $args[] = "-P" . escapeshellarg($port);
    }
}

$args[] = $dbs_tiki;

$args = implode(' ', $args);
$command = "mysqldump --quick $args | gzip -5 > " . escapeshellarg($outputFile);

exec($command);
chmod($outputFile, 0777);

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
