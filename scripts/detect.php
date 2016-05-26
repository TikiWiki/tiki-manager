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
 	$matches2 = array();
	preg_match('/(\d+)(\d{2})(\d{2})$/',$instance->phpversion,$matches2);
	if (array_key_exists(1, $matches2) && array_key_exists(2, $matches2) && array_key_exists(3, $matches2)) {
		printf("Detected PHP : %d.%d.%d\n",$matches2[1],$matches2[2],$matches2[3]);
	}
}
