<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../src/env_setup.php";
include_once dirname(__FILE__) . "/../src/dbsetup.php";

$type = $user = $host = $pass = '';
while( ! in_array( $type, array( 'ftp', 'ssh' ) ) ) {
	$type = strtolower(readline( "Connection type [ssh|ftp] : " ));
}

while( empty( $host ) )
	$host = readline( "Host name : " );

$d_port = ( $type == 'ssh' ) ? 22 : 21;
$port = readline( "Port number : [$d_port] " );
if( empty($port) )
	$port = $d_port;

while( empty( $user ) )
	$user = strtolower(readline( "User : " ));
while( $type == 'ftp' && empty( $pass ) )
	$pass = readline( "Password : " );

$d_name = $host;
$name = $contact = $webroot = $tempdir = $weburl = '';

$name = readline( "Instance name : [$d_name] " );
if( empty( $name ) )
	$name = $d_name;

while( empty( $contact ) )
	$contact = strtolower(readline( "Contact email : " ));

$instance = new Instance;
$instance->name = $name;
$instance->contact = $contact;
$instance->webroot = rtrim( $webroot, '/' );
$instance->weburl = rtrim( $weburl, '/' );
$instance->tempdir = rtrim( $tempdir, '/' );

$instance->save();
echo color("Instance information saved.\n", 'green');

$access = $instance->registerAccessMethod( $type, $host, $user, $pass, $port );

if( ! $access )
{
	$instance->delete();
	echo color("Set-up failure. Instance removed.\n", 'red');
}

info( "Detecting remote configuration." );
if( ! $instance->detectPHP() ){
	if ($instance->phpversion < 50300){
		die( color("PHP Interpreter version is less than 5.3.\n", 'red') );
	}
	else{
		die( color("PHP Interpreter could not be found on remote host.\n", 'red') );
	}
}

$d_linux = $instance->detectDistribution();
echo "You are running on a $d_linux\n";

switch ($d_linux){
	case "ClearOS":
		$d_webroot = ($user=='root'||$user=='apache'?'/var/www/virtual/'.$host.'/':"/home/$user/public_html");
		break;
	default:
		$d_webroot = ($user=='root'||$user=='apache'?'/var/www/html/':"/home/$user/public_html");
}

$d_weburl = "http://$host";
$d_tempdir = "/tmp/trim_temp";

$webroot = readline( "Web root : [$d_webroot] " );
if( empty( $webroot ) )
	$webroot = $d_webroot;

$weburl = readline( "Web URL : [$d_weburl] " );
if( empty( $weburl ) )
	$weburl = $d_weburl;

$tempdir = readline( "Working directory : [$d_tempdir] " );
if( empty( $tempdir ) )
	$tempdir = $d_tempdir;

if( $access instanceof ShellPrompt )
	$access->shellExec( "mkdir -p $tempdir" );
else
	echo color("Shell access is required to create the working directory. You will need to create it manually.\n",'yellow');

$instance->webroot = rtrim( $webroot, '/' );
$instance->tempdir = rtrim( $tempdir, '/' );
$instance->save();
echo color("Instance information saved.\n", 'green');

perform_instance_installation( $instance );
