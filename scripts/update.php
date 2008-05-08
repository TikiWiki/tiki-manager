<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";
include dirname(__FILE__) . "/../src/check.php";

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

	if( $ok )
		$access->shellExec(
			"cd " . escapeshellarg( $instance->webroot ),
			"touch .htaccess",
			"mv .htaccess .htaccess.bak",
			"echo \"Order allow,deny\nDeny from all\" > .htaccess"
		);

	$app = $instance->getApplication();
	$filesToResolve = $app->performUpdate( $instance );
	$version = $instance->getLatestVersion();

	handleCheckResult( $instance, $version, $filesToResolve );

	if( $ok )
		$access->shellExec(
			"cd " . escapeshellarg( $instance->webroot ),
			"mv .htaccess.bak .htaccess"
		);
}

?>
