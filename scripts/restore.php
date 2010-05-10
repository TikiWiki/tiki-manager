<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";
include dirname(__FILE__) . "/../src/dbsetup.php";

define( 'ARG_SWITCH', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'switch' );

$raw = Instance::getInstances();
$instances = array();
$all = array();
foreach( $raw as $instance )
{
	$all[$instance->id] = $instance;

	if( ! $instance->getApplication() )
		$instances[] = $instance;
}

echo "Note: It is only possible to restore a backup on a blank install.\n\n";
echo "Which instance do you want to restore on?\n";

foreach( $instances as $key => $i )
	echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 30 ) . str_pad( $i->contact, 20 ) . "\n";

$selection = readline( ">>> " );
$selection = getEntries( $instances, $selection );

$restorable = Instance::getRestorableInstances();

foreach( $selection as $instance )
{
	info( $instance->name );

	echo "Which instance do you want to restore?\n";

	foreach( $restorable as $key => $i )
		echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 30 ) . str_pad( $i->contact, 20 ) . "\n";

	$single = readline( ">>> " );

	if( ! $single = reset( getEntries( $restorable, $single ) ) )
	{
		warning("No instance selected.");
		continue;
	}

	$files = $single->getArchives();
	echo "Which backup do you want to restore?\n";
	foreach( $files as $key => $path )
		echo "[$key] $path\n";

	$file = readline( ">>> " );
	if( ! $file = reset( getEntries( $files, $file ) ) )
	{
		warning("Skip: No archive file selected.");
		continue;
	}

	info( "Uploading $file" );
	$base = basename( $file );
	$remote = $instance->getWorkPath( $base );

	$access = $instance->getBestAccess('scripting');

	$access->uploadFile( $file, $remote );
	echo $access->shellExec(
		"mkdir -p {$instance->tempdir}/restore",
		"tar -jx -C {$instance->tempdir}/restore -f " . escapeshellarg( $remote )
	);

	info( "Reading manifest" );
	$current = trim(`pwd`);
	chdir( TEMP_FOLDER );
	`tar -jx {$single->id}/manifest.txt -f $file`;
	$manifest = file_get_contents( "{$single->id}/manifest.txt" );
	chdir( $current );

	foreach( explode( "\n", $manifest ) as $line )
	{
		if( empty($line) ) continue;

		list( $hash, $location ) = explode( "    ", $line, 2 );

		$base = basename( $location );

		echo "Previous host used $location\n";
		$location = readline( "New location: [{$instance->webroot}] " );
		if( empty( $location ) ) $location = $instance->webroot;
		$location = escapeshellarg( rtrim( $location, '/' ) );

		$normal = escapeshellarg( $instance->getWorkPath( "restore/{$single->id}/$hash/$base/" ) ) . '*';
		$hidden = escapeshellarg( $instance->getWorkPath( "restore/{$single->id}/$hash/$base/" ) ) . '.*';
		info("Copying files");

		$access->shellExec(
			"mkdir -p $location",
			"mv $normal $location/",
			"mv $hidden $location/"
		);
	}

	$oldVersion = $instance->getLatestVersion();
	$instance->app = $single->app;
	$version = $instance->createVersion();
	$version->type = $oldVersion->type;
	$version->branch = $oldVersion->branch;
	$version->date = $oldVersion->date;
	$version->save();
	$instance->save();
	
	perform_database_setup( $instance, "{$instance->tempdir}/restore/{$single->id}/database_dump.sql" );

	$version->collectChecksumFromInstance( $instance );

	if( $instance->app == 'tikiwiki' )
		$instance->getApplication()->fixPermissions();

	info("Cleaning up");
	echo $access->shellExec(
		"rm -Rf {$instance->tempdir}/restore"
	);
}
