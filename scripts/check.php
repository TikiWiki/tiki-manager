<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../src/env_setup.php";
include_once dirname(__FILE__) . "/../src/check.php";

$instances = Instance::getInstances();

echo "\nHosts you can verify:\n";

$selection = selectInstances( $instances, "(you can select one, multiple, or blank for all)\n");
if( !count( $selection ) ) $selection = $instances;

foreach( $selection as $instance )
{
	$version = $instance->getLatestVersion();

	if( ! $version )
	{
		echo color("Instance [$selection] ({$instance->name}) does not have a registered version. Skip.\n", 'yellow');
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
			$input = promptUser( ">>> ", '' );
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
