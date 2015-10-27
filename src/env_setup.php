<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

if( ! function_exists( 'readline' ) )
{
	function readline( $prompt )
	{
		echo $prompt;
		$fp = fopen("php://stdin","r");
		$line = rtrim(fgets($fp, 1024) );
		return $line;
	}
}

function color( $string, $color )
{
	$avail = array(
		'red' => 31,
		'green' => 32,
		'yellow' => 33,
		'cyan' => 36,
	);

	if( !isset($avail[$color]) )
		return $string;

	return "\033[{$avail[$color]}m$string\033[0m";
}

function info( $text ) { echo color( $text, 'cyan' ) . "\n"; }
function warning( $text ) { echo color( $text, 'yellow' ) . "\n"; }
function error( $text ) { echo color( $text, 'red' ) . "\n"; }

include dirname(__FILE__) . "/ftplib.php";
include dirname(__FILE__) . "/sshlib.php";
include dirname(__FILE__) . "/accesslib.php";
include dirname(__FILE__) . "/instancelib.php";
include dirname(__FILE__) . "/applicationlib.php";
include dirname(__FILE__) . "/databaselib.php";
include dirname(__FILE__) . "/rclib.php";

include dirname(__FILE__) . "/channellib.php";
include dirname(__FILE__) . "/backupreportlib.php";
include dirname(__FILE__) . "/reportlib.php";

include dirname(__FILE__) . "/ext/Password.php";

$root = realpath( dirname(__FILE__) . "/.." );
define( "DB_FILE", "$root/data/trim.db" );
define( "SSH_CONFIG", "$root/data/ssh_config" );
define( "CACHE_FOLDER", "$root/cache" );
define( "TEMP_FOLDER", "$root/tmp" );
define( "RSYNC_FOLDER", "$root/tmp/rsync" );
define( "MOUNT_FOLDER", "$root/tmp/mount" );
define( "BACKUP_FOLDER", "$root/backup" );
define( "ARCHIVE_FOLDER", "$root/backup/archive" );

if( file_exists( "$root/data/id_dsa" ) && file_exists( "$root/data/id_dsa.pub" ) )
{
	define( "SSH_KEY", "$root/data/id_dsa" );
	define( "SSH_PUBLIC_KEY", "$root/data/id_dsa.pub" );
	warning( sprintf(
		"TRIM is using a ssh-dsa key which have been deprecated in openssh-7.0. It is recommended that you remove it (%s and %s). " .
		"After that, run 'make copysshkey' and TRIM will create a new RSA key. Copy the new key to all your instances.",
		SSH_KEY, SSH_PUBLIC_KEY
	) );
}
else
{
	define( "SSH_KEY", "$root/data/id_rsa" );
	define( "SSH_PUBLIC_KEY", "$root/data/id_rsa.pub" );
}

if( array_key_exists( 'EDITOR', $_ENV ) )
	define( 'EDITOR', $_ENV['EDITOR'] );
else
{
	echo "Default editor used (vim). You can change the EDITOR environment variable.\n";
	define( 'EDITOR', 'vim' );
}

if( array_key_exists( 'DIFF', $_ENV ) )
	define( 'DIFF', $_ENV['DIFF'] );
else
{
	echo "Default diff used (diff). You can change the DIFF environment variable.\n";
	define( 'DIFF', 'diff' );
}

// Check for required extensions
if( ! in_array( 'sqlite', PDO::getAvailableDrivers() ) )
	die( "SQLite extension not available in current PHP installation. Impossible to continue.\n" );

// Check for required system dependencies
$kg = `which ssh-keygen`;
$ssh = `which ssh`;
if( empty( $kg ) || empty( $ssh ) )
	die( "SSH tools not installed on current machine. Make sure `ssh-keygen` and `ssh` are available in current path.\n" );

// Make sure SSH is set-up
if( ! file_exists( SSH_KEY ) || ! file_exists( SSH_PUBLIC_KEY ) )
{
	if( ! is_writable( dirname(SSH_KEY) ) )
		die( "Impossible to generate SSH key. Make sure data folder is writable.\n" );

	$key = SSH_KEY;
	`ssh-keygen -t rsa -f $key`;
}

if( ! file_exists( CACHE_FOLDER ) )
	mkdir( CACHE_FOLDER );
if( ! file_exists( TEMP_FOLDER ) )
	mkdir( TEMP_FOLDER );
if( ! file_exists( RSYNC_FOLDER ) )
	mkdir( RSYNC_FOLDER );
if( ! file_exists( MOUNT_FOLDER ) )
	mkdir( MOUNT_FOLDER );
if( ! file_exists( BACKUP_FOLDER ) )
	mkdir( BACKUP_FOLDER );
if( ! file_exists( ARCHIVE_FOLDER ) )
	mkdir( ARCHIVE_FOLDER );

function cache_folder( $app, $version )
{
	$key = sprintf( "%s-%s-%s", $app->getName(), $version->type, $version->branch );
	$key = str_replace( '/', '_', $key );
	$folder = CACHE_FOLDER . "/$key";

	return $folder;
}

// Make sure the raw database exists
if( ! file_exists( DB_FILE ) )
{
	if( ! is_writable( dirname(DB_FILE) ) )
		die( "Impossible to generate database. Make sure data folder is writable.\n" );

	try {
		$db = new PDO( 'sqlite:' . DB_FILE );
	} catch (PDOException $e) {
		die( "Could not create the database for an unknown reason. SQLite said: {$e->getMessage()}\n" );
	}
	
	$db->exec( "CREATE TABLE info ( name VARCHAR(10), value VARCHAR(10), PRIMARY KEY(name) );" );
	$db->exec( "INSERT INTO info ( name, value ) VALUES( 'version', '0' );" );
	$db = null;

	$file = DB_FILE;
}

try {
	$db = new PDO( 'sqlite:' . DB_FILE );
} catch (PDOException $e) {
	die( "Could not connect to the database for an unknown reason. SQLite said: {$e->getMessage()}\n" );
}

// Obtain the current database version
$result = $db->query( "SELECT value FROM info WHERE name = 'version'" );
$version = (int) $result->fetchColumn();

// Update the schema to the latest version
// One case per version, no breaks, no failures
switch( $version ) // {{{
{
case 0:
	$db->exec( "
		CREATE TABLE instance (
			instance_id INTEGER PRIMARY KEY,
			name VARCHAR(25),
			contact VARCHAR(100),
			webroot VARCHAR(100),
			weburl VARCHAR(100),
			tempdir VARCHAR(100),
			phpexec VARCHAR(50),
			app VARCHAR(10)
		);

		CREATE TABLE version (
			version_id INTEGER PRIMARY KEY,
			instance_id INTEGER,
			type VARCHAR(10),
			branch VARCHAR(50),
			date VARCHAR(25)
		);

		CREATE TABLE file (
			version_id INTEGER,
			path VARCHAR(255),
			hash CHAR(32)
		);

		CREATE TABLE access (
			instance_id INTEGER,
			type VARCHAR(10),
			host VARCHAR(50),
			user VARCHAR(25),
			pass VARCHAR(25)
		);

		UPDATE info SET value = '1' WHERE name = 'version';
	" );
case 1:
	$db->exec( "
		CREATE TABLE backup (
			instance_id INTEGER,
			location VARCHAR(200)
		);

		CREATE INDEX version_instance_ix ON version ( instance_id );
		CREATE INDEX file_version_ix ON file ( version_id );
		CREATE INDEX access_instance_ix ON access ( instance_id );
		CREATE INDEX backup_instance_ix ON backup ( instance_id );

		UPDATE info SET value = '2' WHERE name = 'version';
	" );
case 2:
	$db->exec( "
		CREATE TABLE report_receiver (
			instance_id INTEGER PRIMARY KEY,
			user VARCHAR(200),
			pass VARCHAR(200)
		);

		CREATE TABLE report_content (
			receiver_id INTEGER,
			instance_id INTEGER
		);

		CREATE INDEX report_receiver_ix ON report_content ( receiver_id );
		CREATE INDEX report_instance_ix ON report_content ( instance_id );

		UPDATE info SET value = '3' WHERE name = 'version';
	" );
case 3:
	$db->exec( "
		UPDATE access SET host = (host || ':' || '22') WHERE type = 'ssh';
		UPDATE access SET host = (host || ':' || '22') WHERE type = 'ssh::nokey';
		UPDATE access SET host = (host || ':' || '21') WHERE type = 'ftp';

		UPDATE info SET value = '4' WHERE name = 'version';
	" );
} // }}}

// Database access
function query( $query, $params = null ) // {{{
{
	if( is_null( $params ) )
		$params = array();

	foreach( $params as $key => $value )
	{
		if( is_null( $value ) )
			$query = str_replace( $key, 'NULL', $query );
		elseif( is_int( $value ) )
			$query = str_replace( $key, (int) $value, $query );
		else
			$query = str_replace( $key, "'$value'", $query );
	}

	global $db;
	$ret = $db->query( $query );

	if( ! $ret )
		echo $query . "\n";

	return $ret;
} // }}}

function rowid() // {{{
{
	global $db;
	return $db->lastInsertId();
} // }}}

// Tools
function findDigits( $selection ) // {{{
{
	// Accept ranges of type 2-10
	$selection = preg_replace( "/(\d+)-(\d+)/e", "implode( ' ', range( $1, $2 ) )", $selection );
	preg_match_all( "/\d+/", $selection, $parts, PREG_PATTERN_ORDER );

	return $parts[0];
} // }}}

function getEntries( $list, $selection ) // {{{
{
	if( ! is_array( $selection ) )
		$selection = findDigits( $selection );
	
	$output = array();
	foreach( $selection as $index )
		if( array_key_exists( $index, $list ) )
			$output[] = $list[$index];

	return $output;
} // }}}

/**
 * Ask the user to select one or more instances to perform
 * an action.
 *
 * @param array list of Instance objects
 * @param string $selectionQuestion message displayed to the user before the list of available instances
 * @return array one or more instances objects
 */
function selectInstances( array $instances, $selectionQuestion )
{
	echo $selectionQuestion;

	printInstances( $instances );

	$selection = readline( ">>> " );
	$selection = getEntries( $instances, $selection );

	return $selection;
}

/**
 * Print a list of instances to the user for selection.
 *
 * @param array $instances list of Instance objects
 * @return null
 */
function printInstances( array $instances )
{
	foreach( $instances as $key => $i )
	{
		echo "[$key] " . str_pad($i->name, 20) . str_pad($i->weburl, 40) . str_pad($i->contact, 20) . "\n";
	}
}

function php() // {{{
{
	$paths = `locate bin/php`;
	$phps = explode( "\n", $paths );

	// Check different versions
	$valid = array();
	foreach( $phps as $interpreter )
	{
		if( ! in_array( basename( $interpreter ), array( 'php', 'php5' ) ) )
			continue;

		$versionInfo = `$interpreter -v`;
		if( preg_match( "/PHP (\d+\.\d+\.\d+)/", $versionInfo, $parts ) )
			$valid[$parts[1]] = $interpreter;
	}

	// Handle easy cases
	if( count( $valid ) == 0 )
		return null;
	if( count( $valid ) == 1 )
		return reset( $valid );

	// List available options for user
	krsort( $valid );
	return reset( $valid );
} // }}}
