<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";
include dirname(__FILE__) . "/../src/dbsetup.php";

$user = $host = '';
while( empty( $host ) )
	$host = readline( "SSH host name : " );
while( empty( $user ) )
	$user = readline( "SSH user : " );

$name = $contact = $webroot = $tempdir = $weburl = '';

$d_name = $host;
$d_webroot = "/home/$user/public_html";
$d_tempdir = "/home/$user/trim_temp";
$d_weburl = "http://$host";

$name = readline( "Instance name : [$d_name] " );
if( empty( $name ) )
	$name = $d_name;

while( empty( $contact ) )
	$contact = readline( "Contact email : " );

$webroot = readline( "Web root : [$d_webroot] " );
if( empty( $webroot ) )
	$webroot = $d_webroot;

$weburl = readline( "Web URL : [$d_weburl] " );
if( empty( $weburl ) )
	$weburl = $d_weburl;

$tempdir = readline( "Working directory : [$d_tempdir] " );
if( empty( $tempdir ) )
	$tempdir = $d_tempdir;

$instance = new Instance;
$instance->name = $name;
$instance->contact = $contact;
$instance->webroot = rtrim( $webroot, '/' );
$instance->weburl = rtrim( $weburl, '/' );
$instance->tempdir = rtrim( $tempdir, '/' );

$instance->save();
echo color("Instance information saved.\n", 'green');

$access = $instance->registerAccessMethod( 'ssh', $host, $user );

if( ! $access )
{
	$instance->delete();
	echo color("Set-up failure. Instance removed.\n", 'red');
}

if( $access instanceof ShellPrompt )
	$access->shellExec( "mkdir -p $tempdir" );
else
	echo color("Shell access is required to create the working directory. You will need to create it manually.\n",'yellow');

info( "Detecting remote configuration." );
if( ! $instance->detectPHP() )
	die( color("PHP Interpreter could not be found on remote host.\n", 'red') );

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

	info( "Installing application." );
	echo color("If for any reason the installation fails (ex: wrong setup.sh parameters for tikiwiki), you can use `make access` to complete the installation manually.\n", 'yellow');
	$version = reset( $selection );
	$app->install( $version );

	if( $app->requiresDatabase() )
		perform_database_setup( $instance );
}

?>
