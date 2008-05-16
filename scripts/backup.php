<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";
include dirname(__FILE__) . "/../src/clean.php";

$all = Instance::getInstances();

$instances = array();
foreach( $all as $instance )
	if( $instance->getBestAccess( 'scripting' ) instanceof ShellPrompt )
		$instances[] = $instance;

echo "Note: Backups are only available on SSH instances.\n\n";
echo "Which instances do you want to backup?\n";

foreach( $instances as $key => $i )
	echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 30 ) . str_pad( $i->contact, 20 ) . "\n";

$selection = readline( ">>> " );
$selection = getEntries( $instances, $selection );

foreach( $selection as $instance )
{
	$instance->backup();
}

perform_archive_cleanup();

?>
