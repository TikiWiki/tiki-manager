<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";
include dirname(__FILE__) . "/../src/check.php";

$instances = Instance::getInstances();

echo "Hosts you can verify:\n";
foreach( $instances as $key => $i )
	echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 30 ) . str_pad( $i->contact, 20 ) . "\n";

$selection = readline( "\nWhich ones do you want to verify? (can select multiple, blank for all) " );

$selection = findDigits( $selection );
if( empty( $selection ) )
	$selection = array_keys( $instances );

$selection = getEntries( $instances, $selection );

foreach( $selection as $instance )
{
	$version = $instance->getLatestVersion();

	if( ! $version )
	{
		echo "Instance [$id] ({$instance->name}) does not have a registered version. Skip.\n";
		continue;
	}

	if( $version->hasChecksums() )
		handleCheckResult( $instance, $version, $version->performCheck( $instance ) );
	else
	{
		$input = '';
		while( ! in_array( $input, array( 'current', 'source', 'skip' ) ) )
		{
			echo "No checksums exist. What do you want to do?\n[current] Use the files currently online for checksum\n[source] Get checksums from repository (best option)\n[skip] Do nothing\n";
			$input = readline( ">>> " );
		}

		switch( $input )
		{
		case 'source':
			$version->collectChecksumFromSource( $instance );
			handleCheckResult( $instance, $version, $version->performCheck( $instance ) );
			break;

		case 'current':
			$version->collectChecksumFromInstance( $instance );
			break;

		case 'skip':
			continue;
		}
	}
}

?>
