<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class SSH_Host
{
	private static $resources = array();

	private $host;
	private $user;

	function __construct( $host, $user )
	{
		$this->host = $host;
		$this->user = $user;
	}

	private function getExtHandle()
	{
		if( ! function_exists( 'ssh2_connect' ) )
			return false;

		$host = $this->host;
		$user = $this->user;

		$key = "$user@$host";
		
		if( isset( self::$resources[$key] ) )
			return self::$resources[$key];

		$handle = @ssh2_connect( $host );
		
		if( ! $handle )
			return self::$resources[$key] = false;

		if( ! @ssh2_auth_pubkey_file( $handle, $user, SSH_PUBLIC_KEY, SSH_KEY ) )
			return self::$resources[$key] = false;

		return self::$resources[$key] = $handle;
	}

	function setupKey( $publicKeyFile )
	{
		if( function_exists( 'ssh2_connect' ) )
		{
			// Check key presence first if possible
			if( $this->getExtHandle() )
				return;
			else
				// Pretend this check never happened, connection will be
				// succesful after key set-up
				unset( self::$resources["{$this->user}@{$this->host}"] );
		}

		$file = escapeshellarg( $publicKeyFile );
		$host = escapeshellarg( "{$this->user}@{$this->host}" );
		`ssh-copy-id -i $file $host`;
	}

	function runCommands( $commands ) {
		if( ! is_array( $commands ) )
			$commands = func_get_args();

		if( $handle = self::getExtHandle( $this->host, $this->user ) )
		{
			$content = '';

			foreach( $commands as $line )
			{
				$stream = ssh2_exec( $handle, $line );
				stream_set_blocking( $stream, true );

				$content .= stream_get_contents($stream);
			}

			return trim( $content );
		}
		else
		{
			$key = SSH_KEY;
			$config = SSH_CONFIG;

			$string = implode( " && ", $commands );
			$fullcommand = escapeshellarg( $string );

			$output = trim( `ssh -i $key -F $config {$this->user}@{$this->host} $fullcommand` );

			return $output;
		}
	}

	function sendFile( $localFile, $remoteFile )
	{
		if( $handle = self::getExtHandle( $this->host, $this->user ) )
		{
			ssh2_scp_send( $handle, $localFile, $remoteFile, 0644 );
		}
		else
		{
			$localFile = escapeshellarg( $localFile );
			$remoteFile = escapeshellarg( $remoteFile );

			$key = SSH_KEY;
			`scp -i $key $localFile {$this->user}@{$this->host}:$remoteFile`;
			$this->runCommands( "chmod 0644 $remoteFile" );
		}
	}

	function receiveFile( $remoteFile, $localFile )
	{
		if( $handle = self::getExtHandle( $this->host, $this->user ) )
		{
			ssh2_scp_send( $handle, $remoteFile, $localFile );
		}
		else
		{
			$localFile = escapeshellarg( $localFile );
			$remoteFile = escapeshellarg( $remoteFile );

			$key = SSH_KEY;
			`scp -i $key {$this->user}@{$this->host}:$remoteFile $localFile`;
		}
	}

	function openShell()
	{
		$key = SSH_KEY;
		passthru( "ssh -i $key {$this->user}@{$this->host}" );
	}

	function rsync( $remoteLocation, $localMirror )
	{
		$user = $this->user;
		$host = $this->host;
		$key = SSH_KEY;
		
		`rsync -aL -e "ssh -i $key -l $user" $user@$host:$remoteLocation $localMirror`;
	}
}

?>
