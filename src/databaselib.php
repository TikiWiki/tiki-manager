<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

interface Database_Adapter
{
	function createDatabase( Instance $instance, $name );
	function createUser( Instance $instance, $username, $password );
	function finalize( Instance $instance );
	function grantRights( Instance $instance, $username, $database );
	function getSupportedExtensions();
}

class Database
{
	private $instance;
	private $adapter;
	private $extensions = array();

	public $host;
	public $user;
	public $pass;
	public $dbname;
	public $type;

	function __construct( Instance $instance, Database_Adapter $adapter ) // {{{
	{
		$this->instance = $instance;
		$this->adapter = $adapter;
		$this->locateExtensions();
	} // }}}

	private function locateExtensions() // {{{
	{
		$access = $this->instance->getBestAccess( 'scripting' );
		$content = $access->shellExec( "{$this->instance->phpexec} -m" );
		$content = str_replace( "\r", '', $content );
		$modules = explode( "\n", $content );

		$this->extensions = array_intersect( $modules, array(
			'mysqli',
			'mysql',
			'pdo_mysql',
			'sqlite',
			'pdo_sqlite',
		) );
	} // }}}

	function getUsableExtensions() // {{{
	{
		return array_intersect(
			$this->instance->getApplication()->getAcceptableExtensions(),
			$this->extensions,
			$this->adapter->getSupportedExtensions() );
	} // }}}

	function createAccess( $prefix ) // {{{
	{
		$this->pass = Text_Password::create( 12, 'unpronounceable' );
		$this->user = "{$prefix}_user";
		$this->dbname = "{$prefix}_db";

		$this->adapter->createDatabase( $this->instance, $this->dbname );
		$this->adapter->createUser( $this->instance, $this->user, $this->pass );
		$this->adapter->grantRights( $this->instance, $this->user, $this->dbname );
		$this->adapter->finalize( $this->instance );
	} // }}}
}

class Database_Adapter_Dummy implements Database_Adapter
{
	function __construct( $host ) // {{{
	{
	} // }}}

	function createDatabase( Instance $instance, $name ) // {{{
	{
	} // }}}

	function createUser( Instance $instance, $username, $password ) // {{{
	{
	} // }}}

	function grantRights( Instance $instance, $username, $database ) // {{{
	{
	} // }}}

	function finalize( Instance $instance ) // {{{
	{
	} // }}}

	function getSupportedExtensions() // {{{
	{
		return array( 'mysqli', 'mysql', 'pdo_mysql', 'sqlite', 'pdo_sqlite' );
	} // }}}
}

class Database_Adapter_Mysql implements Database_Adapter
{
	private $args;
	private $host;

	function __construct( $host, $masterUser, $masterPassword ) // {{{
	{
		$this->host = $host;
		$args = array();
		
		$args[] = "-h " . escapeshellarg( $host );
		$args[] = "-u " . escapeshellarg( $masterUser );
		if( $masterPassword )
			$args[] = "-p " . escapeshellarg( $masterPassword );

		$this->args = implode( " ", $args );
	} // }}}

	function createDatabase( Instance $instance, $name ) // {{{
	{
		$access = $instance->getBestAccess( 'scripting' );
		$access->shellExec( "mysqladmin {$this->args} create $name" );
	} // }}}

	function createUser( Instance $instance, $username, $password ) // {{{
	{
		$u = mysql_real_escape_string( $username );
		$p = mysql_real_escape_string( $password );
		$query = escapeshellarg( "CREATE USER '$u'@'{$this->host}' IDENTIFIED BY '$p';" );

		$access = $instance->getBestAccess( 'scripting' );
		$access->shellExec( "echo $query | mysql {$this->args}" );
	} // }}}

	function grantRights( Instance $instance, $username, $database ) // {{{
	{
		$u = mysql_real_escape_string( $username );
		$d = mysql_real_escape_string( $database );
		$query = escapeshellarg( "GRANT ALL ON `$d`.* TO '$u'@'{$this->host}';" );

		$access = $instance->getBestAccess( 'scripting' );
		$access->shellExec( "echo $query | mysql {$this->args}" );
	} // }}}

	function finalize( Instance $instance ) // {{{
	{
		$access = $instance->getBestAccess( 'scripting' );
		$access->shellExec( "mysqladmin {$this->args} reload" );
	} // }}}

	function getSupportedExtensions() // {{{
	{
		return array( 'mysqli', 'mysql', 'pdo_mysql' );
	} // }}}
}

?>
