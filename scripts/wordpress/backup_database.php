<?php
if( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
	$_SERVER['argv'] = $_GET;
}

$root = $_SERVER['argv'][1];
$outputFile = $_SERVER['argv'][2];

require($root . '/wp-config.php');

$args = array();

if (DB_USER) {
	$args[] = "-u" . escapeshellarg(DB_USER);
}

if (DB_PASSWORD) {
	$args[] = "-p" . escapeshellarg(DB_PASSWORD);
}

if (DB_HOST) {
	$args[] = "-h" . escapeshellarg(DB_HOST);
}

$args[] = DB_NAME;

$args = implode(' ', $args);
$command = "mysqldump --quick $args | gzip -5 > " . escapeshellarg($outputFile);

exec($command);
chmod($outputFile, 0777);
