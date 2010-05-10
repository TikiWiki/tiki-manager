<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class SVN
{
	private $repository;

	function __construct( $repository )
	{
		$this->repository = $repository;
	}

	function updateInstanceTo( Instance $instance, $path )
	{
		$access = $instance->getBestAccess( 'scripting' );
		if( ! $access instanceof ShellPrompt )
			return false;

		$info = $this->getRepositoryInfo( $instance, $access );

		if( isset( $info['root'] ) && $info['root'] != $this->repository )
			return false;

		info( "Performing SVN update on remote host." );
		$full = "{$this->repository}/$path";
		$escaped = escapeshellarg( $full );

		if( !isset( $info['url'] ) || $info['url'] == $full )
			$access->shellExec( "svn up --non-interactive " . escapeshellarg( $instance->webroot ) );
		else
		{
			$access->shellExec( 
				"svn up --non-interactive " . escapeshellarg( $instance->getWebPath('temp') ),
				"svn switch --non-interactive $escaped " . escapeshellarg( $instance->webroot )
			);
		}
	}

	private function getRepositoryInfo( $instance, $access )
	{
		$remoteText = $access->shellExec( 'svn info ' . escapeshellarg( $instance->webroot ), 'sleep 1' );
		if( empty( $remoteText ) )
			return array();

		$info = array();

		$raw = explode( "\n", $remoteText );

		foreach( $raw as $line )
		{
			list( $key, $value ) = explode( ':', $line, 2 );
			$key = trim( $key );
			$value = trim( $value );

			switch( $key )
			{
			case 'URL':
				$info['url'] = $value;
				break;
			case 'Repository Root':
				$info['root'] = $value;
				break;
			}
		}

		return $info;
	}

	function getRepositoryBranch( Instance $instance )
	{
		$access = $instance->getBestAccess( 'scripting' );
		if( ! $access instanceof ShellPrompt )
			return false;

		$info = $this->getRepositoryInfo( $instance, $access );
		
		if( isset( $info['url'] ) )
			return substr( $info['url'], strlen( $this->repository ) + 1 );
	}
}

class CVS
{
	private $protocol;
	private $user;
	private $host;
	private $root;
	private $module;

	function __construct( $protocol, $user, $host, $root, $module )
	{
		$this->protocol = $protocol;
		$this->user = $user;
		$this->host = $host;
		$this->root = $root;
		$this->module = $module;
	}

	function updateInstanceTo( Instance $instance, $tag )
	{
		$access = $instance->getBestAccess( 'scripting' );
		if( ! $access instanceof ShellPrompt )
			return false;
		
		info( "Performing CVS update on remote host." );
		$rep = escapeshellarg( ":{$this->protocol}:{$this->user}@{$this->host}:{$this->root}" );
		$access->chdir( $instance->webroot );
		$access->setenv( 'CVS_RSH', 'ssh' );
		$access->shellExec( 
			"cvs -d$rep up -d -r " . escapeshellarg( $tag )
		);
	}
}
