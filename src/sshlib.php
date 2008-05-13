<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class SSH_Host
{
	private $host;
	private $user;

	function __construct( $host, $user )
	{
		$this->host = $host;
		$this->user = $user;
	}

	function setupKey( $publicKeyFile )
	{
		$file = escapeshellarg( $publicKeyFile );
		$host = escapeshellarg( "{$this->user}@{$this->host}" );
		`ssh-copy-id -i $file $host`;
	}

	function runCommands( $commands ) {
		if( ! is_array( $commands ) )
			$commands = func_get_args();

		$string = implode( " && ", $commands );
		$fullcommand = escapeshellarg( $string );

		$key = SSH_KEY;

		$output = trim( `ssh -i $key {$this->user}@{$this->host} $fullcommand` );

		return $output;
	}

	function sendFile( $localFile, $remoteFile )
	{
		$localFile = escapeshellarg( $localFile );
		$remoteFile = escapeshellarg( $remoteFile );

		$key = SSH_KEY;
		`scp -i $key $localFile {$this->user}@{$this->host}:$remoteFile`;
	}

	function receiveFile( $remoteFile, $localFile )
	{
		$localFile = escapeshellarg( $localFile );
		$remoteFile = escapeshellarg( $remoteFile );

		$key = SSH_KEY;
		`scp -i $key {$this->user}@{$this->host}:$remoteFile $localFile`;
	}

	function openShell()
	{
		$key = SSH_KEY;
		passthru( "ssh -i $key {$this->user}@{$this->host}" );
	}
}

?>
