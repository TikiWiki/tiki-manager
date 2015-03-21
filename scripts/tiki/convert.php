<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../../src/env_setup.php";

$raw = Instance::getUpdatableInstances();
$instances = array();
foreach( $raw as $instance )
	if( $instance->getLatestVersion()->type == 'cvs' )
		$instances[] = $instance;

warning( "Make sure you have a recent backup before performing this operation. Some customizations and data may be lost. Note: Only instances running Tiki can be converted." );
echo "Which instances do you want to convert from CVS 1.9 to SVN 2.0?\n";

foreach( $instances as $key => $i )
	echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 30 ) . str_pad( $i->contact, 20 ) . "\n";

$selection = readline( ">>> " );
$selection = getEntries( $instances, $selection );

foreach( $selection as $instance )
{
	$access = $instance->getBestAccess( 'scripting' );
	if( ! $ok = $access instanceof ShellPrompt )
	{
		warning( "Skipping instance {$instance->name}. Shell access required." );
		continue;
	}

	$locked = $instance->lock();

	$toKeep = array(
		'db/local.php',
		'img/wiki',
		'img/wiki_up',
	);

	$store = array();
	$restore = array();
	foreach( $toKeep as $filename )
	{
		$file = escapeshellarg( $instance->getWebPath( $filename ) );
		$temp = escapeshellarg( $instance->getWorkPath( md5( $instance->name . $filename ) ) );

		$store[] = "mv $file $temp";
		$restore[] = "cp -R $temp $file";
		$restore[] = "rm -Rf $temp";
	}

	info('Copying user data on drive');
	$access->shellExec( $store );

	$maint = escapeshellarg( $instance->getWebPath( 'maintenance.html' ) );
	$temp = escapeshellarg( $instance->getWorkPath( 'maintenance.html' ) );

	info('Removing 1.9 files');
	$access->shellExec( 
		"mv $maint $temp",
		"rm -Rf " . escapeshellarg($instance->webroot) . '/*',
		"mv $temp $maint" );

	info('Obtaining 2.0 sources');
	$app = $instance->getApplication();
	$app->install( Version::buildFake( 'svn', 'branches/2.0' ) );

	info('Restoring user data on drive');
	$access->shellExec( $restore );

	$filesToResolve = $app->performUpdate( $instance );

	if( $locked )
		$instance->unlock();
}
