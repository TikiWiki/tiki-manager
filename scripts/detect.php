<?php

include_once dirname(__FILE__) . "/../src/env_setup.php";
include_once dirname(__FILE__) . "/../src/check.php";
include_once dirname(__FILE__) . "/../src/dbsetup.php";

$instances = Instance::getInstances();
$selection = selectInstances( $instances, "Which instances do you want to detect?\n" );

foreach( $selection as $instance )
{
	if( ! $instance->detectPHP() ){
		if ($instance->phpversion < 50300){
			die( color("PHP Interpreter version is less than 5.3.\n", 'red') );
		}
		else{
			die( color("PHP Interpreter could not be found on remote host.\n", 'red') );
		}
	}

	perform_instance_installation( $instance );
}
