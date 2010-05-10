<?php

class FTP_Host
{
	private $host;
	private $user;
	private $pass;
	private $conn;

	function __construct( $host, $user, $pass ) // {{{
	{
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
	} // }}}

	function __destruct() // {{{
	{
		if( $this->conn ) {
			ftp_close( $this->conn );
		}
	} // }}}

	function connect() // {{{
	{
		if( $this->conn )
			return;

		$conn = ftp_connect( $this->host, 21, 15 );
		if( $conn ) {
			if( ftp_login( $conn, $this->user, $this->pass ) ) {
				$this->conn = $conn;
				return true;
			}
		}

		return false;
	} // }}}

	function fileExists( $filename ) // {{{
	{
		$this->connect();

		$dir = dirname( $filename );
		$base = basename( $filename );
		
		$list = ftp_nlist( $this->conn, $dir );

		if( in_array( $filename, $list ) || in_array( $base, $list ) ) {
			return true;
		} else {
			$list = ftp_nlist( $this->conn, "-a $dir" );

			return in_array( $filename, $list ) || in_array( $base, $list );
		}
	} // }}}

	function getContent( $filename ) // {{{
	{
		$this->connect();

		$fp = tmpfile();
		if( ftp_fget( $this->conn, $fp, $filename, FTP_ASCII ) ) {
			$content = '';
			rewind( $fp );
			while( ! feof( $fp ) ) {
				$content .= fread( $fp, 8192 );
			}

			return $content;
		}
	} // }}}

	function sendFile( $localFile, $remoteFile ) // {{{
	{
		$this->connect();
		ftp_put( $this->conn, $remoteFile, $localFile, FTP_BINARY );
		ftp_chmod( $this->conn, 0644, $remoteFile );
	} // }}}

	function chmod( $level, $remoteFile ) // {{{
	{
		$this->connect();
		ftp_chmod( $this->conn, $level, $remoteFile );
	} // }}}

	function receiveFile( $remoteFile, $localFile ) // {{{
	{
		$this->connect();
		ftp_get( $this->conn, $localFile, $remoteFile, FTP_BINARY );
	} // }}}

	function removeFile( $remoteFile ) // {{{
	{
		$this->connect();
		ftp_delete( $this->conn, $remoteFile );
	} // }}}

	function getPWD() // {{{
	{
		$this->connect();
		return ftp_pwd( $this->conn );
	} // }}}

	function rename( $from, $to ) // {{{
	{
		$this->connect();
		return ftp_rename( $this->conn, $from, $to );
	} // }}}
}
