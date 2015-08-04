<?php
if( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] == 'GET' ) {
	$_SERVER['argv'] = $_GET;
}

$root = $_SERVER['argv'][1];

include $root . '/db/local.php';

$args = array();
if( $user_tiki )
	$args[] = "-u" . escapeshellarg( $user_tiki );
if( $pass_tiki )
	$args[] = "-p" . escapeshellarg( $pass_tiki );

if( $host_tiki ) {
    $parts = explode( ';', $host_tiki );

    $args[] = "-h" . escapeshellarg( $parts[0] );

    // parse the MySQL port from a DSN string
    if( isset( $parts[1] ) && strpos( $parts[1], 'port=' ) !== false ) {
        $port = substr( $parts[1], 5 );
        $args[] = "-P" . escapeshellarg( $port );
    }
}

$args[] = $dbs_tiki;

$args = implode( ' ', $args );
$command = "echo \"select distinct value from tiki_preferences where name like '%use_dir' union select att_store_dir from tiki_forums\" | mysql -f $args";

echo exec( $command );
