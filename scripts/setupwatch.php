<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";

$email = readline( "Email address to contact: " );
$time = readline( "What time should it run at? [00:00] " );

list( $hour, $minute ) = explode( ":", $time );

$hour = (int) $hour;
$minute = (int) $minute;

if( ! in_array( $hour, range( 0, 23 ) ) )
	die( "Invalid hour.\n" );
if( ! in_array( $minute, range( 0, 59 ) ) )
	die( "Invalid minute.\n" );

$path = realpath( dirname(__FILE__) . '/watch.php' );

file_put_contents( 
	$file = TEMP_FOLDER . '/crontab',
	"$minute $hour * * * php -d memory_limit=256M $path $email\n" );

`crontab $file`;

?>
