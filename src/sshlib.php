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
		$content = file_get_contents( $publicKeyFile );
		$content = trim( $content );
		$this->runCommands(
			"echo \"$content\" >> ~/.ssh/authorized_keys"
		);
	}

	function runCommands( $commands ) {
		if( ! is_array( $commands ) )
			$commands = func_get_args();

		$string = implode( "\n", $commands );

		$name = tempnam( '/tmp', 'command' );
		file_put_contents( $name, $string );
		$key = SSH_KEY;

		$output = trim( `ssh -i $key {$this->user}@{$this->host} < $name` );

		`rm $name`;

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
}

?>
