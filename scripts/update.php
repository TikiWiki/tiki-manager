<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../src/env_setup.php";
include_once dirname(__FILE__) . "/../src/check.php";
include_once dirname(__FILE__) . "/../src/dbsetup.php";

define( 'ARG_SWITCH', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'switch' );
define( 'ARG_AUTO', $_SERVER['argc'] > 2 && $_SERVER['argv'][1] == 'auto' );

$instances = Instance::getUpdatableInstances();

if( ARG_AUTO ) {
	$selection = getEntries( $instances, implode( ' ', array_slice( $_SERVER['argv'], 2 ) ) );
} else {
	echo "Note: Only CVS and SVN instances can be updated.\n\n";
	echo "Which instances do you want to update?\n";

	foreach( $instances as $key => $i )
		echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 40 ) . str_pad( $i->contact, 20 ) . "\n";

	$selection = readline( ">>> " );
	$selection = getEntries( $instances, $selection );
}


foreach( $selection as $instance )
{
	echo "Working on ".$instance->name."\n";
	$locked = $instance->lock();
	$instance->detectPHP();
	$app = $instance->getApplication();

	ob_start();
	perform_instance_installation( $instance );
	$contents = $string = trim(preg_replace('/\s\s+/', ' ', ob_get_contents()));
	ob_end_clean();
	$ms = array();
	preg_match('/(\d+\.|trunk)/', $contents, $ms);

	if( ARG_SWITCH )
	{
		$versions_raw = $app->getVersions();
		$versions = array();
		foreach( $versions_raw as $version )
			if( $version->type == 'svn' )
				$versions[] = $version;
		echo "Which version do you want to upgrade to?\n";
		$i = 0;
		foreach( $versions as $key => $version ){
                        preg_match('/(\d+\.|trunk)/',$version->branch, $matches);
                        if ((($matches[0] >= 13) || ($matches[0] == 'trunk')) && ($instance->phpversion < 50500) ||
			($matches[0] < $ms[0])
			){
                                // none to do, this match is incompatible
                        }
                        else {
                                echo "[$key] {$version->type} : {$version->branch}\n";
				$i++;
			}
                }

		if ($i){
			$input = readline( ">>> " );
			$versionSel = getEntries( $versions, $input );
			if( empty( $versionSel ) && ! empty( $input ) )
				$target = Version::buildFake( 'svn', $input );
			else
				$target = reset( $versionSel );

			if( count( $versionSel ) > 0 ){
				$filesToResolve = $app->performUpdate( $instance, $target );
				$version = $instance->getLatestVersion();
				handleCheckResult( $instance, $version, $filesToResolve );
			}
			else
				warning( "No version selected. Nothing to perform." );
		}
		else
			warning( "No upgrades are available. This is likely because you are already at the latest version permitted by the server." );
	}
	else
	{
		$filesToResolve = $app->performUpdate( $instance );
		$version = $instance->getLatestVersion();
		handleCheckResult( $instance, $version, $filesToResolve );
	}

	if( $locked )
		$instance->unlock();
}
