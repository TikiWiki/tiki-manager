<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../src/env_setup.php";

$time = readline( "What time should it run at? [00:00] " );

list( $hour, $minute ) = explode( ":", $time );

$hour = (int) $hour;
$minute = (int) $minute;

if( ! in_array( $hour, range( 0, 23 ) ) )
	die( "Invalid hour.\n" );
if( ! in_array( $minute, range( 0, 59 ) ) )
	die( "Invalid minute.\n" );

$path = realpath( dirname(__FILE__) . '/update.php' );

$instances = Instance::getUpdatableInstances();

echo "Note: Only CVS and SVN instances can be updated.\n\n";
echo "Which instances do you want to update?\n";

printInstances( $instances );

$which = readline( ">>> " );

file_put_contents( 
	$file = TEMP_FOLDER . '/crontab',
	`crontab -l` . "$minute $hour * * * " . php() . " -d memory_limit=256M $path auto $which\n" );

echo "If adding to crontab fails and blocks, hit Ctrl-C and add these parameters manually.\n\t$minute $hour * * * " . php() . " -d memory_limit=256M $path auto $which\n";
`crontab $file`;
