<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../src/env_setup.php";
include_once dirname(__FILE__) . "/../src/dbsetup.php";
define( 'ARG_BLANK', $_SERVER['argc'] == 2 && $_SERVER['argv'][1] == 'blank' );

echo color("\nAnswer the following to add a new TRIM instance.\n\n", 'yellow');

$type = $user = $host = $pass = '';

$type = strtolower(
    promptUser('Connection type', null, array('ftp', 'local', 'ssh'))
);

if ($type != 'local') {
    while( empty( $host ) )
        $host = promptUser('Host name');

	$d_port = ( $type == 'ssh' ) ? 22 : 21;
	$port = promptUser('Port number', $d_port);

	while( empty( $user ) )
		$user = strtolower(promptUser('User'));
	while( $type == 'ftp' && empty( $pass ) ) {
		print "Password : ";
		$pass = getPassword(true); print "\n";
    }

	$d_name = $host;
}
else if ($type == 'local') {
	$user = 'trim';
	$pass = '';
	$host = 'localhost';
	$port = 0;
	$d_name = 'localhost';
}

$name = $contact = $webroot = $tempdir = $weburl = '';

$name = promptUser('Instance name', $d_name);
$contact = strtolower(promptUser('Contact email'));

$instance = new Instance;
$instance->name = $name;
$instance->contact = $contact;
$instance->webroot = rtrim( $webroot, '/' );
$instance->weburl = rtrim( $weburl, '/' );
$instance->tempdir = rtrim( $tempdir, '/' );

if ($type == 'ftp'){
	$d_weburl = "http://$host";
	$d_tempdir = "/tmp/trim_temp";
	$d_webroot = "/home/$user/public_html";

	$webroot = promptUser('Web root', $d_webroot);
    $weburl = promptUser('Web URL', $d_weburl);

	$instance->webroot = rtrim( $webroot, '/' );
	$instance->weburl = rtrim( $weburl, '/' );
}

$instance->save();
echo color("Instance information saved.\n", 'green');

$access = $instance->registerAccessMethod( $type, $host, $user, $pass, $port );

if( ! $access )
{
	$instance->delete();
	echo color("Set-up failure. Instance removed.\n", 'red');
}

info( "Detecting remote configuration." );
if( ! $instance->detectSVN() ){	
		die( color("Subversion not detected on the remote server\n", 'red') );
}

if( ! $instance->detectPHP() ){
	die( color("PHP Interpreter could not be found on remote host.\n", 'red') );
}
else{
	if ($instance->phpversion < 50300){
		die( color("PHP Interpreter version is less than 5.3.\n", 'red') );
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
if ($type != 'ftp'){
	$webroot = promptUser('Web root', $d_webroot);
	$weburl = promptUser('Web URL', $d_weburl);
}
$tempdir = promptUser('Working directory', $d_tempdir);

if( $access instanceof ShellPrompt )
	$access->shellExec( "mkdir -p $tempdir" );
else
	echo color("Shell access is required to create the working directory. You will need to create it manually.\n",'yellow');

$instance->weburl = rtrim( $weburl, '/' );
$instance->webroot = rtrim( $webroot, '/' );
$instance->tempdir = rtrim( $tempdir, '/' );

$instance->save();
echo color("Instance information saved.\n", 'green');

if( ARG_BLANK ) 
	echo color("This is a blank (empty) instance. This is useful to restore a backup later.\n", 'blue');
else {
	perform_instance_installation( $instance );
	echo color ("Please test your site at {$instance->weburl}\n", 'blue');
}
