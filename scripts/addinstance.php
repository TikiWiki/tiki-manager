<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";
$name = $contact = $webroot = $tempdir = $weburl = '';

while( empty( $name ) )
	$name = readline( "Instance name : " );
while( empty( $contact ) )
	$contact = readline( "Contact email : " );
while( empty( $webroot ) )
	$webroot = readline( "Web root : " );
while( empty( $weburl ) )
	$weburl = readline( "Web URL : " );
while( empty( $tempdir ) )
	$tempdir = readline( "Working directory : " );

$instance = new Instance;
$instance->name = $name;
$instance->contact = $contact;
$instance->webroot = $webroot;
$instance->weburl = $weburl;
$instance->tempdir = $tempdir;

$instance->save();
echo "Instance information saved.\n";

$user = $host = '';
while( empty( $host ) )
	$host = readline( "SSH host name : " );
while( empty( $user ) )
	$user = readline( "SSH user : " );

$access = $instance->registerAccessMethod( 'ssh', $host, $user );

if( ! $access )
{
	$instance->delete();
	echo "Set-up failure. Instance removed.\n";
}

if( ! $instance->detectPHP() )
	die( "PHP Interpreter could not be found on remote host.\n" );

if( ! $app = $instance->findApplication() )
{
	$apps = Application::getApplications( $instance );
	echo "No applications were found on remote host.\n";
	echo "Which one do you want to install? (none to skip)\n";
	foreach( $apps as $key => $app )
		echo "[$key] {$app->getName()}\n";

	$selection = readline( ">>> " );
	$selection = getEntries( $apps, $selection );
	if( empty( $selection ) )
		die( "No instance to install.\n" );

	$app = reset( $selection );

	$versions = $app->getVersions();
	echo "Which version do you want to install? (none to skip)\n";
	foreach( $versions as $key => $version )
		echo "[$key] {$version->type} : {$version->branch}\n";

	$selection = readline( ">>> " );
	$selection = getEntries( $versions, $selection );
	if( empty( $selection ) )
		die( "No version to install.\n" );

	echo "If for any reason the installation fails (ex: wrong setup.sh parameters for tikiwiki), you can use `make access` to complete the installation manually.\n";
	$version = reset( $selection );
	$app->install( $version );
}

?>
