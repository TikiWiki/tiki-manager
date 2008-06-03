<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

abstract class Access
{
	private $rowid;
	private $type;
	protected $instance;

	public $host;
	public $user;
	public $password;

	static function getClassFor( $type ) // {{{
	{
		if( $type == 'ssh' )
			return 'Access_SSH';
		elseif( $type == 'ssh::nokey' )
			return 'Access_SSH';
		else
			die( "Unknown type: $type\n" );
	} // }}}

	static function getAccessFor( Instance $instance ) // {{{
	{
		$result = query( "SELECT rowid, type, host, user, pass password FROM access WHERE instance_id = :id", 
			array( ':id' => $instance->id ) );

		$access = array();
		while( $row = sqlite_fetch_array( $result ) )
		{
			$class = self::getClassFor( $row['type'] );

			$a = new $class( $instance );
			$a->host = $row['host'];
			$a->user = $row['user'];
			$a->password = $row['password'];

			$access[] = $a;
		}

		return $access;
	} // }}}
	
	function __construct( Instance $instance, $type ) // {{{
	{
		$this->instance = $instance;
		$this->type = $type;
	} // }}}

	function save() // {{{
	{
		$params = array(
			':instance' => $this->instance->id,
			':rowid' => $this->rowid,
			':type' => $this->type,
			':host' => $this->host,
			':user' => $this->user,
			':pass' => $this->password,
		);

		query( "INSERT OR REPLACE INTO access (instance_id, rowid, type, user, host, pass) VALUES( :instance, :rowid, :type, :user, :host, :pass )", $params );
		$rowid = rowid();
		if( ! $this->rowid && $rowid )
			$this->rowid = $rowid;
	} // }}}

	function changeType( $type ) // {{{
	{
		if( strpos( $type, "{$this->type}::" ) === 0 )
		{
			$this->type = $type;
			return true;
		}
		else
			return false;
	} // }}}

	abstract function firstConnect();

	abstract function getInterpreterPath();

	abstract function fileExists( $filename );

	abstract function fileGetContents( $filename );

	abstract function fileModificationDate( $filename );

	abstract function runPHP( $localFile );

	abstract function downloadFile( $filename );

	abstract function uploadFile( $filename, $remoteLocation );

	abstract function deleteFile( $filename );

	abstract function localizeFolder( $remoteLocation, $localMirror );
}

interface ShellPrompt {
	function shellExec( $command );

	function openShell();

	function hasExecutable( $name );
}

class Access_SSH extends Access implements ShellPrompt
{
	function __construct( Instance $instance )
	{
		parent::__construct( $instance, 'ssh' );
	}

	function firstConnect() // {{{
	{
		$host = new SSH_Host( $this->host, $this->user );
		$host->setupKey( SSH_PUBLIC_KEY );

		echo "Testing connection...\n";

		$host->runCommands( "exit" );

		$answer = '';
		while( ! in_array( $answer, array( 'yes', 'no' ) ) )
			$answer = readline( "After successfully entering your password, were you asked for a password again? [yes|no] " );

		if( $answer == 'yes' )
			$this->changeType( 'ssh::nokey' );

		return true;
	} // }}}

	function getInterpreterPath() // {{{
	{
		$host = new SSH_Host( $this->host, $this->user );
		
		// Make sure we can find paths
		$locateExists = $host->runCommands( 'which locate' );
		if( empty( $locateExists ) )
			return null;

		// Get possible paths
		$phps = $host->runCommands( 'locate bin/php' );
		$phps = explode( "\n", $phps );

		// Check different versions
		$valid = array();
		foreach( $phps as $interpreter )
		{
			if( ! in_array( basename( $interpreter ), array( 'php', 'php5' ) ) )
				continue;

			$versionInfo = $host->runCommands( "$interpreter -v" );
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
		$versions = array_keys( $valid );
		echo "Multiple PHP interpreters available on host :\n";
		$counter = 0;
		foreach( $valid as $version => $path )
		{
			echo "[$counter] $path ($version)\n";
			$counter++;
		}

		// Ask user
		$counter--;
		$selection = -1;
		while( ! array_key_exists( $selection, $versions ) )
			$selection = readline( "Which version do you want to use? [0-$counter] " );

		$version = $versions[$selection];
		return $valid[$version];
	} // }}}

	function fileExists( $filename ) // {{{
	{
		$host = new SSH_Host( $this->host, $this->user );

		$base = basename( $filename );
		$dir = dirname( $filename );

		$base = escapeshellarg( $base );
		$dir = escapeshellarg( $dir );

		$output = $host->runCommands(
			"cd $dir",
			"ls | grep $base" );

		return ! empty( $output );
	} // }}}

	function fileGetContents( $filename ) // {{{
	{
		$host = new SSH_Host( $this->host, $this->user );
		
		$filename = escapeshellarg( $filename );
		return $host->runCommands( "cat $filename" );
	} // }}}

	function fileModificationDate( $filename ) // {{{
	{
		$host = new SSH_Host( $this->host, $this->user );

		$root = escapeshellarg( $filename );

		$data = $host->runCommands( "ls -l $root" );

		if( preg_match( "/\d{4}-\d{2}-\d{2}/", $data, $parts ) )
			return $parts[0];
		else
			return null;
	} // }}}

	function runPHP( $localFile, $arg = '' ) // {{{
	{
		$host = new SSH_Host( $this->host, $this->user );

		$remoteName = md5( $localFile );
		$remoteFile = $this->instance->getWorkPath( $remoteName );

		$host->sendFile( $localFile, $remoteFile );
		$output = $host->runCommands(
			"{$this->instance->phpexec} -q -d memory_limit=256M {$remoteFile} {$arg}",
			"rm {$remoteFile}" );

		return $output;
	} // }}}

	function downloadFile( $filename ) // {{{
	{
		if( $filename{0} != '/' )
			$filename = $this->instance->getWebPath( $filename );

		$dot = strrpos( $filename, '.' );
		$ext = substr( $filename, $dot );

		$local = tempnam( TEMP_FOLDER, 'trim' );

		$host = new SSH_Host( $this->host, $this->user );
		$host->receiveFile( $filename, $local );

		rename( $local, $local . $ext );
		chmod( $local . $ext, 0644 );

		return $local . $ext;
	} // }}}

	function uploadFile( $filename, $remoteLocation ) // {{{
	{
		$host = new SSH_Host( $this->host, $this->user );
		if( $remoteLocation{0} == '/' )
			$host->sendFile( $filename, $remoteLocation );
		else
			$host->sendFile( $filename, $this->instance->getWebPath( $remoteLocation ) );
	} // }}}

	function deleteFile( $filename ) // {{{
	{
		if( $filename{0} != '/' )
			$filename = $this->instance->getWebPath( $filename );

		$path = escapeshellarg( $filename );

		$host = new SSH_Host( $this->host, $this->user );
		$host->runCommands( "rm $path" );
	} // }}}

	function shellExec( $commands ) // {{{
	{
		if( ! is_array( $commands ) )
			$commands = func_get_args();

		$host = new SSH_Host( $this->host, $this->user );
		return $host->runCommands( $commands );
	} // }}}

	function openShell() // {{{
	{
		$host = new SSH_Host( $this->host, $this->user );
		$host->openShell();
	} // }}}

	function hasExecutable( $command ) // {{{
	{
		$command = escapeshellcmd( $command );
		$exists = $this->shellExec( "which $command" );

		return ! empty( $exists );
	} // }}}

	function localizeFolder( $remoteLocation, $localMirror ) // {{{
	{
		$host = new SSH_Host( $this->host, $this->user );
		$host->rsync( $remoteLocation, $localMirror );
	} // }}}
}


?>
