<?php

include_once dirname(__FILE__) . "/../src/env_setup.php";
include_once dirname(__FILE__) . "/../src/check.php";
include_once dirname(__FILE__) . "/../src/dbsetup.php";

$instances = Instance::getInstances();
$selection = selectInstances( $instances, "Which instances do you want to detect?\n" );

foreach( $selection as $instance )
{
	if( ! $instance->detectPHP() )
		die( "PHP Interpreter could not be found on remote host.\n" );

	perform_instance_installation( $instance );
}
