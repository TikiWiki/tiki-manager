<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";
include dirname(__FILE__) . "/../src/clean.php";

$instances = Instance::getInstances();

if( ! isset( $_SERVER['argv'][1] ) )
{
	echo color("Note: Backups are only available on SSH instances.\n\n", 'yellow');
	echo "Which instances do you want to backup?\n";

	foreach( $instances as $key => $i )
		echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 30 ) . str_pad( $i->contact, 20 ) . "\n";

	$selection = readline( ">>> " );
	$selection = getEntries( $instances, $selection );
}
elseif( $_SERVER['argv'][1] == 'all' )
{
	$selection = $instances;
}
else
{
	$selection = getEntries( $instances, implode( ' ', array_slice( $_SERVER['argv'], 1 ) ) );
}

foreach( $selection as $instance )
{
	info( "Performing backup for {$instance->name}" );
	$instance->backup();
}

perform_archive_cleanup();
