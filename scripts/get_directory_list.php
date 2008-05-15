<?php

$root = $_SERVER['argv'][1];

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
$command = "echo \"select distinct value from tiki_preferences where name like '%use_dir' union select att_store_dir from tiki_forums\" | mysql -f $args";

exec( $command );

?>
