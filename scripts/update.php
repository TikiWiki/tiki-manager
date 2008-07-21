<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";
include dirname(__FILE__) . "/../src/check.php";

define( 'ARG_SWITCH', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'switch' );

$instances = Instance::getUpdatableInstances();

echo "Note: Only CVS and SVN instances can be updated.\n\n";
echo "Which instances do you want to update?\n";

foreach( $instances as $key => $i )
	echo "[$key] " . str_pad( $i->name, 20 ) . str_pad( $i->weburl, 30 ) . str_pad( $i->contact, 20 ) . "\n";

$selection = readline( ">>> " );
$selection = getEntries( $instances, $selection );

foreach( $selection as $instance )
{
	$access = $instance->getBestAccess( 'scripting' );
	if( ! $ok = $access instanceof ShellPrompt )
		echo "Site will not be disabled during the update. Shell access required.\n";

	$url = "{$instance->weburl}/maintenance.html";
	$htaccess = <<<HTACCESS
RewriteEngine On

RewriteRule . maintenance.html
HTACCESS;
	$htaccess = escapeshellarg( $htaccess );

	if( $ok )
	{
		info( "Locking website." );
		if( ! $access->fileExists( $instance->getWebPath( 'maintenance.html' ) ) )
			$access->uploadFile( dirname(__FILE__) . "/maintenance.html", "maintenance.html" );

		$access->shellExec(
			"cd " . escapeshellarg( $instance->webroot ),
			"touch .htaccess",
			"mv .htaccess .htaccess.bak",
			"echo $htaccess > .htaccess"
		);
	}

	$app = $instance->getApplication();

	if( ARG_SWITCH )
	{
		$versions_raw = $app->getVersions();
		$versions = array();
		foreach( $versions_raw as $version )
			if( $version->type == 'svn' )
				$versions[] = $version;
		echo "Which version do you want to install? (none to skip)\n";
		foreach( $versions as $key => $version )
			echo "[$key] {$version->type} : {$version->branch}\n";

		$versionSel = readline( ">>> " );
		$versionSel = getEntries( $versions, $versionSel );

		if( count( $versionSel ) > 0 )
		{
			$target = reset( $versionSel );
			$filesToResolve = $app->performUpdate( $instance, $target );
			$version = $instance->getLatestVersion();
			handleCheckResult( $instance, $version, $filesToResolve );
		}
		else
			warning( "No version selected. Nothing to perform." );
	}
	else
	{
		$filesToResolve = $app->performUpdate( $instance );
		$version = $instance->getLatestVersion();
		handleCheckResult( $instance, $version, $filesToResolve );
	}

	if( $ok )
	{
		info( "Unlocking website." );
		$access->shellExec(
			"cd " . escapeshellarg( $instance->webroot ),
			"mv .htaccess.bak .htaccess"
		);
	}
}

?>
