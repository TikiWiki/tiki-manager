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
		elseif( $type == 'ftp' )
			return 'Access_FTP';
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

	abstract function runPHP( $localFile, $args = array() );

	abstract function downloadFile( $filename );

	abstract function uploadFile( $filename, $remoteLocation );

	abstract function deleteFile( $filename );

	abstract function moveFile( $remoteSource, $remoteTarget );

	abstract function localizeFolder( $remoteLocation, $localMirror );
}

interface ShellPrompt
{
	function shellExec( $command );

	function openShell();

	function chdir( $location );

	function setenv( $var, $value );

	function hasExecutable( $name );
}

interface Mountable
{
	function mount( $target );
	function umount();
	function synchronize( $source, $mirror, $keepFolderName = false );
}

class Access_SSH extends Access implements ShellPrompt
{
	private $location;
	private $env = array();

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
		
		$sets = array(
			array( 'which php', 'which php5', 'which php4' ),
			array( 'locate bin/php' ),
		);

		foreach( $sets as $attempt )
		{
			// Get possible paths
			$phps = $host->runCommands( $attempt );
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
				continue;
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
		}
	} // }}}

	function fileExists( $filename ) // {{{
	{
		if( $filename{0} != '/' )
			$filename = $this->instance->getWebPath( $filename );

		$eFile = escapeshellarg( $filename );
		$output = $this->shellExec( "ls $eFile" );

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

	function runPHP( $localFile, $args = array() ) // {{{
	{
		$host = new SSH_Host( $this->host, $this->user );

		$remoteName = md5( $localFile );
		$remoteFile = $this->instance->getWorkPath( $remoteName );

		$host->sendFile( $localFile, $remoteFile );
		$arg = implode( ' ', array_map( 'escapeshellarg', $args ) );
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

	function moveFile( $remoteSource, $remoteTarget ) // {{{
	{
		if( $remoteSource{0} != '/' )
			$remoteSource = $this->instance->getWebPath( $remoteSource );
		if( $remoteTarget{0} != '/' )
			$remoteTarget = $this->instance->getWebPath( $remoteTarget );

		$a = escapeshellarg( $remoteSource );
		$b = escapeshellarg( $remoteTarget );

		$this->shellExec( "mv $a $b" );
	} // }}}

	function chdir( $location ) // {{{
	{
		$this->location = $location;
	} // }}}

	function setenv( $var, $value ) // {{{
	{
		$this->env[$var] = $value;
	} // }}}

	function shellExec( $commands ) // {{{
	{
		if( ! is_array( $commands ) )
			$commands = func_get_args();

		$host = new SSH_Host( $this->host, $this->user );
		if( $this->location )
			$host->chdir( $this->location );
		foreach( $this->env as $key => $value )
			$host->setenv( $key, $value );

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

class Access_FTP extends Access implements Mountable
{
	private $lastMount;

	function __construct( Instance $instance )
	{
		parent::__construct( $instance, 'ftp' );
	}

	function openShell() // {{{
	{
		echo "User: {$this->user}, Pass: {$this->password}\n";
		passthru( "ftp {$this->host}" );
	} // }}}

	function firstConnect() // {{{
	{
		$conn = new FTP_Host( $this->host, $this->user, $this->password );

		return $conn->connect();
	} // }}}

	function getInterpreterPath() // {{{
	{
		$result = $this->runPHP( dirname(__FILE__) . '/../scripts/checkversion.php' );

		if( preg_match( '/^[5-9]\./', $result ) ) {
			return 'mod_php';
		}
	} // }}}

	function fileExists( $filename ) // {{{
	{
		if( $filename{0} != '/' )
			$filename = $this->instance->getWebPath( $filename );

		$ftp = new FTP_Host( $this->host, $this->user, $this->password );
		return $ftp->fileExists( $filename );
	} // }}}

	function fileGetContents( $filename ) // {{{
	{
		$ftp = new FTP_Host( $this->host, $this->user, $this->password );
		return $ftp->getContent( $filename );
	} // }}}

	function fileModificationDate( $filename )
	{
	}

	function runPHP( $localFile, $args = array() ) // {{{
	{
		foreach( $args as & $potentialPath ) {
			if( $potentialPath{0} == '/' ) {
				$potentialPath = $this->obtainRelativePathTo( $potentialPath, $this->instance->webroot );
			}
		}

		$host = new FTP_Host( $this->host, $this->user, $this->password );

		$remoteName = 'trim_' . md5( $localFile ) . '.php';
		$remoteFile = $this->instance->getWebPath( $remoteName );

		array_unshift( $args, null );
		$arg = http_build_query( $args, '', '&' );

		$host->sendFile( $localFile, $remoteFile );
		$output = file_get_contents( $this->instance->getWebUrl( $remoteName ) . '?' . $arg );

		$host->removeFile( $remoteFile );

		return $output;
	} // }}}

	function downloadFile( $filename ) // {{{
	{
		if( $filename{0} != '/' )
			$filename = $this->instance->getWebPath( $filename );

		$dot = strrpos( $filename, '.' );
		$ext = substr( $filename, $dot );

		$local = tempnam( TEMP_FOLDER, 'trim' );

		$host = new FTP_Host( $this->host, $this->user, $this->password );
		$host->receiveFile( $filename, $local );

		rename( $local, $local . $ext );
		chmod( $local . $ext, 0644 );

		return $local . $ext;
	} // }}}

	function uploadFile( $filename, $remoteLocation ) // {{{
	{
		$host = new FTP_Host( $this->host, $this->user, $this->password );
		if( $remoteLocation{0} == '/' )
			$host->sendFile( $filename, $remoteLocation );
		else
			$host->sendFile( $filename, $this->instance->getWebPath( $remoteLocation ) );
	} // }}}

	function moveFile( $remoteSource, $remoteTarget ) // {{{
	{
		if( $remoteSource{0} != '/' )
			$remoteSource = $this->instance->getWebPath( $remoteSource );
		if( $remoteTarget{0} != '/' )
			$remoteTarget = $this->instance->getWebPath( $remoteTarget );

		$host = new FTP_Host( $this->host, $this->user, $this->password );
		$host->rename( $remoteSource, $remoteTarget );
	} // }}}

	function deleteFile( $filename ) // {{{
	{
		if( $filename{0} != '/' )
			$filename = $this->instance->getWebPath( $filename );

		$host = new FTP_Host( $this->host, $this->user, $this->password );
		$host->removeFile( $filename );
	} // }}}

	function localizeFolder( $remoteLocation, $localMirror ) // {{{
	{
		if( $remoteLocation{0} != '/' ) {
			$remoteLocation = $this->instance->getWebPath( $remoteLocation );
		}

		$compress = in_array( 'zlib', $this->instance->getExtensions() );

		$name = md5(time()) . '.tar';
		if( $compress ) {
			$name .= '.gz';
		}

		$remoteTar = $this->instance->getWebPath( $name );
		$this->runPHP( dirname(__FILE__) . '/../scripts/package_tar.php', array($remoteTar, $remoteLocation) );

		$localized = $this->downloadFile( $remoteTar );
		$this->deleteFile( $remoteTar );

		$current = getcwd();
		if( ! file_exists( $localMirror ) ) {
			mkdir( $localMirror );
		}
		
		chdir( $localMirror );

		$eLoc = escapeshellarg( $localized );
		if( $compress ) {
			passthru( "tar -zxf $eLoc" );
		} else {
			`tar -xf $eLoc`;
		}

		chdir( $current );
	} // }}}

	static function obtainRelativePathTo( $targetFolder, $originFolder ) // {{{
	{
		$parts = array();
		while( ( 0 !== strpos( $targetFolder, $originFolder ) ) 
			&& $originFolder != '/' 
			&& $originFolder != '' ) {
			$originFolder = dirname( $originFolder );
			$parts [] = '..';
		}

		$out = null;
		if( strpos( $targetFolder, $originFolder ) === 0 ) {
			// Target is under the origin
			$relative = substr( $targetFolder, strlen( $originFolder ) );
			$out = ltrim( implode( '/', $parts ) . '/' . ltrim( $relative, '/' ), '/' );
		}

		if( empty( $out ) ) {
			$out = '.';
		}

		return $out;
	} // }}}

	function mount( $target ) // {{{
	{
		if( $this->lastMount )
			return false;

		$ftp = new FTP_Host( $this->host, $this->user, $this->password );
		$pwd = $ftp->getPWD();
		$toRoot = preg_replace( "/\w+/", '..', $pwd );

		$this->lastMount = $target;

		$remote = escapeshellarg( "ftp://{$this->user}:{$this->password}@{$this->host}$toRoot" );
		$local = escapeshellarg( $target );

		$cmd = "curlftpfs $remote $local";
		shell_exec($cmd);

		return true;
	} // }}}

	function umount() // {{{
	{
		if( $this->lastMount ) {
			$loc = escapeshellarg( $this->lastMount );
			`sudo umount $loc`;
			$this->lastMount = null;
		}
	} // }}}

	function synchronize( $source, $mirror, $keepFolderName = false ) // {{{
	{
		$source = rtrim( $source, '/' ) . ($keepFolderName ? '' : '/');
		$mirror = rtrim( $mirror, '/' ) . '/';

		$source = escapeshellarg( $source );
		$target = escapeshellarg( $mirror );
		$tmp = escapeshellarg( RSYNC_FOLDER );
		$cmd = "rsync -rDu --no-p --no-g --size-only --exclude .svn --exclude copyright.txt --exclude changelog.txt --temp-dir=$tmp $source $target";
		passthru($cmd);
	} // }}}

	function copyLocalFolder( $localFolder, $remoteFolder = '' ) // {{{
	{
		if( $remoteFolder{0} != '/' ) {
			$remoteFolder = $this->instance->getWebPath( $remoteFolder );
		}

		$compress = in_array( 'zlib', $this->instance->getExtensions() );

		$current = getcwd();
		chdir( $localFolder );

		$temp = TEMP_FOLDER;
		$name = md5(time()) . '.tar';
		`tar --exclude=.svn -cf $temp/$name *`;
		if( $compress ) {
			`gzip -5 $temp/$name`;
			$name .= '.gz';
		}

		chdir( $current );

		$this->uploadFile( "$temp/$name", $name );
		unlink( "$temp/$name" );

		$this->runPHP( dirname(__FILE__) . '/../scripts/extract_tar.php', array($name, $remoteFolder) );

		$this->deleteFile( $name );
	} // }}}
}
