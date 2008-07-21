<?php

$root = $_SERVER['argv'][1];

include $root . '/db/local.php';

$files = array();

switch( $dbversion_tiki )
{
case '1.9':
	$files[] = $root . '/db/tiki_1.8to1.9.sql';
case '1.10':
case '2.0':
	$files[] = $root . '/db/tiki_1.9to1.10.sql';
	$files[] = $root . '/db/tiki_1.9to2.0.sql';
default:
	$prev = $dbversion_tiki - 1;

	$files[] = $root . "/db/tiki_$prev.0to$dbversion_tiki.sql";
}

$args = array();
if( $user_tiki )
	$args[] = "-u" . escapeshellarg( $user_tiki );
if( $pass_tiki )
	$args[] = "-p" . escapeshellarg( $pass_tiki );
if( $host_tiki )
	$args[] = "-h" . escapeshellarg( $host_tiki );

$args[] = $dbs_tiki;

$args = implode( ' ', $args );

foreach( $files as $file )
{
	if( ! file_exists( $file ) )
		continue;

	$command = "mysql -f $args < $file";
	exec( $command );
}

?>
