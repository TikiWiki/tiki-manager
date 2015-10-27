<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../src/env_setup.php";
include_once dirname(__FILE__) . "/../src/clean.php";

$instances = Instance::getInstances();

if( ! isset( $_SERVER['argv'][1] ) )
{
	echo color("Note: Backups are only available on SSH instances.\n\n", 'yellow');
	$selection = selectInstances( $instances, "Which instances do you want to backup?\n" );
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
