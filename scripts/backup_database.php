<?php

$root = $_SERVER['argv'][1];
$outputFile = $_SERVER['argv'][2];

include $root . '/db/local.php';

$args = array();
if( $user_tiki )
	$args[] = "-u" . escapeshellarg( $user_tiki );
if( $pass_tiki )
	$args[] = "-p" . escapeshellarg( $pass_tiki );
if( $host_tiki )
	$args[] = "-h" . escapeshellarg( $host_tiki );

$args[] = $dbs_tiki;

$args = implode( ' ', $args );
$command = "mysqldump --extended-insert $args > " . escapeshellarg( $outputFile );

exec( $command );

?>
