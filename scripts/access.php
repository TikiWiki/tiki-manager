<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../src/env_setup.php";
include_once dirname(__FILE__) . "/../src/check.php";

$instances = Instance::getInstances();
$selection = selectInstances( $instances, "Which instances do you want to access?\n" );

foreach( $selection as $instance )
{
	echo "Connecting to {$instance->name}... (use `exit` to move to next instance)\n";
	$access = $instance->getBestAccess( 'scripting' );
	$access->openShell();
}
