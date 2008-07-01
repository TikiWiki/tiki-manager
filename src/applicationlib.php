<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

abstract class Application
{
	protected $instance;

	public static function getApplications( Instance $instance ) // {{{
	{
		$objects = array();

		$dir = dirname(__FILE__) . "/appinfo";
		$files = scandir( $dir );

		$apps = array();
		foreach( $files as $file )
			if( preg_match( "/^(\w+)\.php$/", $file, $parts ) )
				$apps[] = $parts[1];

		foreach( $apps as $name )
		{
			$classname = 'Application_' . ucfirst( $name );
			if( ! class_exists( $classname ) )
				require "$dir/$name.php";

			$objects[] = new $classname( $instance );
		}

		return $objects;
	} // }}}

	function __construct( Instance $instance )
	{
		$this->instance = $instance;
	}

	abstract function getName();

	abstract function getVersions();

	abstract function isInstalled();

	abstract function install( Version $version );

	abstract function getInstallType();

	abstract function getBranch();

	abstract function getUpdateDate();

	abstract function getSourceFile( Version $version, $filename );

	abstract function performActualUpdate( Version $version );

	abstract function extractTo( Version $version, $folder );

	abstract function getFileLocations();

	abstract function requiresDatabase();

	abstract function getAcceptableExtensions();

	abstract function setupDatabase( Database $database );

	function performUpdate( Instance $instance ) // {{{
	{
		$current = $instance->getLatestVersion();
		$oldFiles = $current->getFileMap();

		$new = $instance->createVersion();
		$new->type = $current->type;
		$new->branch = $current->branch;
		$new->date = date( 'Y-m-d' );
		$new->save();
		info( "Obtaining latest checksum from source." );
		$new->collectChecksumFromSource( $instance );

		$this->performActualUpdate( $new );

		info( "Obtaining remote checksums." );
		$array = $new->performCheck( $instance );
		$newF = $modF = $delF = array();

		foreach( $array['new'] as $file => $hash )
		{
			// If unknown file was known in old version, accept it
			if( array_key_exists( $file, $oldFiles ) && $oldFiles[$file] == $hash )
				$new->recordFile( $hash, $file, $this );
			else
				$newF[$file] = $hash;
		}

		foreach( $array['mod'] as $file => $hash )
		{
			// If modified file was in the same state in previous version
			if( array_key_exists( $file, $oldFiles ) && $oldFiles[$file] == $hash )
				$new->replaceFile( $hash, $file, $this );
			else
				$modF[$file] = $hash;
		}

		// Consider all missing files as conflicts
		$delF = $array['del'];

		return array(
			'new' => $newF,
			'mod' => $modF,
			'del' => $delF,
		);
	} // }}}

	function registerCurrentInstallation() // {{{
	{
		if( ! $this->isInstalled() )
			return null;

		$this->instance->app = $this->getName();
		$this->instance->save();

		$update = $this->instance->createVersion();
		$update->type = $this->getInstallType();
		$update->branch = $this->getBranch();
		$update->date = $this->getUpdateDate();
		$update->save();

		return $update;
	} // }}}
}

?>
