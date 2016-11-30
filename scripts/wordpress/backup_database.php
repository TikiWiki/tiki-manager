<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET')
    $_SERVER['argv'] = $_GET;

$root = $_SERVER['argv'][1];
$outputFile = $_SERVER['argv'][2];

function getWpDbRegex($name)
{
    return "/define\(['\"]{$name}['\"]\s*,\s*['\"](.+?)['\"]\s*\)\s*;/";
}

$wpConfigContents = file("{$root}/wp-config.php");

foreach ($wpConfigContents as $line) {
    $matches = array();
    
    if (preg_match(getWpDbRegex('DB_NAME'), $line, $matches))
        $dbName = $matches[1];
    
    if (preg_match(getWpDbRegex('DB_USER'), $line, $matches))
        $dbUser = $matches[1];
    
    if (preg_match(getWpDbRegex('DB_PASSWORD'), $line, $matches))
        $dbPassword = $matches[1];
    
    if (preg_match(getWpDbRegex('DB_HOST'), $line, $matches))
        $dbHost = $matches[1];
}

$args = array();

if ($dbUser)
    $args[] = '-u' . escapeshellarg($dbUser);

if ($dbPassword)
    $args[] = '-p' . escapeshellarg($dbPassword);

if ($dbHost)
    $args[] = '-h' . escapeshellarg($dbHost);

$args[] = $dbName;

$args = implode(' ', $args);
$command = "mysqldump --quick $args | gzip -5 > " . escapeshellarg($outputFile);

exec($command);
chmod($outputFile, 0777);

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
