<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../../src/env_setup.php";
include_once dirname(__FILE__) . "/../../src/check.php";

$all = Instance::getInstances();

$instances = array();
foreach( $all as $instance )
	if( $instance->getApplication() instanceof Application_Tiki )
		$instances[] = $instance;

echo "Note: Only instances running Tiki can get their permissions fixed.\n\n";
echo "Which instances do you want to fix?\n";

foreach( $instances as $key => $i )
	echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 40 ) . str_pad( $i->contact, 20 ) . "\n";

$selection = readline( ">>> " );
$selection = getEntries( $instances, $selection );

foreach( $selection as $instance )
{
	info( "Fixing permissions for {$instance->name}" );
	$instance->getApplication()->fixPermissions();
}