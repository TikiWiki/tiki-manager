<?php

include dirname(__FILE__) . "/../src/env_setup.php";
include dirname(__FILE__) . "/../src/check.php";

$instances = Instance::getInstances();

echo "Hosts you can detect on:\n";
foreach( $instances as $key => $i )
	echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 30 ) . str_pad( $i->contact, 20 ) . "\n";

$selection = readline( "\nWhich ones do you want to detect? " );

$selection = getEntries( $instances, $selection );

foreach( $selection as $instance )
{
	if( ! $instance->detectPHP() )
		die( 'PHP Interpreter could not be found on remote host.' );

	if( ! $app = $instance->findApplication() )
		die( 'No known application found in web root' );
}

?>
