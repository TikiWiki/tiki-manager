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

		// --non-interactive not supported for info on older versions
		$remoteXml = $access->shellExec( 'svn info --xml ' . escapeshellarg( $instance->webroot ), 'sleep 1' );
		if( empty( $remoteXml ) )
			return false;

		$info = simplexml_load_string( $remoteXml );
		$rep = (string) $info->entry->repository->root;
		$url = (string) $info->entry->url;

		if( $this->repository != $rep )
			return false;

		$full = "{$this->repository}/$path";
		$escaped = escapeshellarg( $full );
		if( $url == $full )
			$access->shellExec( "cd " . escapeshellarg( $instance->webroot ), "svn up --non-interactive" );
		else
			$access->shellExec( "cd " . escapeshellarg( $instance->webroot ), "svn switch --non-interactive $escaped ." );
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

		$rep = escapeshellarg( ":{$this->protocol}:{$this->user}@{$this->host}:{$this->root}" );
		$access->shellExec( 
			"cd " . escapeshellarg( $instance->webroot ),
			"export CVS_RSH=ssh",
			"cvs -d$rep up -d -r " . escapeshellarg( $tag )
		);
	}
}

?>
