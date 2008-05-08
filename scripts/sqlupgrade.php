<?php

$root = $_SERVER['argv'][1];

include $root . '/db/local.php';

$file = $root . '/db/tiki_1.8to1.9.sql';
if( $dbversion_tiki == '1.10' )
	$file = $root . '/db/tiki_1.9to1.10.sql';

$args = array();
if( $user_tiki )
	$args[] = "-u" . escapeshellarg( $user_tiki );
if( $pass_tiki )
	$args[] = "-p" . escapeshellarg( $pass_tiki );
if( $host_tiki )
	$args[] = "-h" . escapeshellarg( $host_tiki );

$args[] = $dbs_tiki;

$args = implode( ' ', $args );
$command = "mysql -f $args < $file";

exec( $command );

?>
