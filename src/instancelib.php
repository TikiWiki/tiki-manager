<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class Instance
{
	private $id;
	public $name;
	public $contact;
	public $webroot;
	public $weburl;
	public $tempdir;
	public $phpexec;
	public $app;

	private $access = array();

	static function getInstances() // {{{
	{
		$result = query( "SELECT instance_id id, name, contact, webroot, weburl, tempdir, phpexec, app FROM instance" );

		$instances = array();
		while( $instance = sqlite_fetch_object( $result, 'Instance' ) )
		{
			$instances[] = $instance;
		}

		return $instances;
	} // }}}

	static function getUpdatableInstances() // {{{
	{
		$result = query( "
			SELECT instance.instance_id id, name, contact, webroot, weburl, tempdir, phpexec, app 
			FROM 
				instance
				INNER JOIN version ON instance.instance_id = version.instance_id
				INNER JOIN (
					SELECT MAX(version_id) version
					FROM version
					GROUP BY instance_id
				) t ON t.version = version.version_id
			WHERE type = 'cvs' OR type = 'svn'" );

		$instances = array();
		while( $instance = sqlite_fetch_object( $result, 'Instance' ) )
		{
			$instances[] = $instance;
		}

		return $instances;
	} // }}}

	function save() // {{{
	{
		$params = array(
			':id' => $this->id,
			':name' => $this->name,
			':contact' => $this->contact,
			':web' => $this->webroot,
			':url' => $this->weburl,
			':temp' => $this->tempdir,
			':phpexec' => $this->phpexec,
			':app' => $this->app,
		);

		query( "INSERT OR REPLACE INTO instance (instance_id, name, contact, webroot, weburl, tempdir, phpexec, app) VALUES( :id, :name, :contact, :web, :url, :temp, :phpexec, :app )", $params );
		$rowid = rowid();
		if( ! $this->id && $rowid )
			$this->id = $rowid;
	} // }}}

	function delete() // {{{
	{
		query( "DELETE FROM instance WHERE instance_id = :id", array( ':id' => $this->id ) );
	} // }}}

	function registerAccessMethod( $type, $host, $user ) // {{{
	{
		if( ! in_array( $type, array( 'ssh' ) ) )
			die( "Unsupported access method.\n" );

		$access = new Access_SSH( $this );
		$access->host = $host;
		$access->user = $user;

		if( $access->firstConnect() )
		{
			$access->save();

			$this->access[] = $access;
			return $access;
		}
	} // }}}

	function getBestAccess( $type ) // {{{
	{
		if( empty( $this->access ) )
			$this->access = Access::getAccessFor( $this );

		// TODO : Add intelligence as more access types get added
		// types :
		//		scripting
		//		filetransfer
		return reset( $this->access );
	} // }}}

	function getWebPath( $relativePath ) // {{{
	{
		$path = "{$this->webroot}/$relativePath";
		$path = str_replace( '/./', '/', $path );

		return $path;
	} // }}}

	function getWorkPath( $relativePath ) // {{{
	{
		return "{$this->tempdir}/$relativePath";
	} // }}}

	function detectPHP() // {{{
	{
		$access = $this->getBestAccess( 'scripting' );
		$path = $access->getInterpreterPath();

		if( strlen( $path ) > 0 )
		{
			$this->phpexec = $path;
			$this->save();

			return true;
		}

		return false;
	} // }}}

	function findApplication() // {{{
	{
		foreach( Application::getApplications( $this ) as $app )
		{
			if( $app->isInstalled() )
			{
				$app->registerCurrentInstallation();
				return $app;
			}
		}

		return null;
	} // }}}

	function createVersion() // {{{
	{
		return new Version( $this->id );
	} // }}}

	function getLatestVersion() // {{{
	{
		$result = query( "
			SELECT version_id id, instance_id, type, branch, date
			FROM version
			WHERE instance_id = :id
			ORDER BY version_id DESC
			LIMIT 1",
			array( ':id' => $this->id ) );

		$object = sqlite_fetch_object( $result, 'Version' );

		return $object;
	} // }}}

	function getApplication() // {{{
	{
		$class = 'Application_' . ucfirst( $this->app );

		$dir = dirname(__FILE__) . "/appinfo";
		if( ! class_exists( $classname ) )
			require_once "$dir/{$this->app}.php";

		return new $class( $this );
	} // }}}

	function backup() // {{{
	{
		$access = $this->getBestAccess( 'scripting' );
		$locations = $this->getApplication()->getFileLocations();

		$approot = BACKUP_FOLDER . '/' . $this->id;
		if( ! file_exists( $approot ) )
			mkdir( $approot );

		`rm $approot/manifest.txt`;
		foreach( $locations as $remote )
		{
			$hash = md5( $remote );
			$locmirror = $approot . '/' . $hash;
			$access->localizeFolder( $remote, $locmirror );
			`echo "$hash    $remote" >> $approot/manifest.txt`;
		}

		// TODO : Archive files
	} // }}}

	function __get( $name ) // {{{
	{
		if( isset( $this->$name ) )
			return $this->$name;
	} // }}}
}

class Version
{
	private $id;
	private $instanceId;
	public $type;
	public $branch;
	public $date;

	public static function buildFake( $type, $branch )
	{
		$v = new self;
		$v->type = $type;
		$v->branch = $branch;
		$v->date = date( 'Y-m-d' );

		return $v;
	}
	function __construct( $instanceId = null ) // {{{
	{
		$this->instanceId = $instanceId;
	} // }}}

	function save() // {{{
	{
		$params = array(
			':id' => $this->id,
			':instance' => $this->instanceId,
			':type' => $this->type,
			':branch' => $this->branch,
			':date' => $this->date,
		);

		query( "INSERT OR REPLACE INTO version (version_id, instance_id, type, branch, date) VALUES( :id, :instance, :type, :branch, :date )", $params );
		$rowid = rowid();
		if( ! $this->id && $rowid )
			$this->id = $rowid;
	} // }}}

	function hasChecksums() // {{{
	{
		$result = query( "SELECT COUNT(*) FROM file WHERE version_id = :id", array( ':id' => $this->id ) );

		return sqlite_fetch_single( $result ) > 0;
	} // }}}

	function performCheck( Instance $instance ) // {{{
	{
		$access = $instance->getBestAccess( 'scripting' );
		$output = $access->runPHP( dirname(__FILE__) . '/../scripts/generate_md5_list.php', $instance->webroot );
		
		$known = $this->getFileMap();

		$newFiles = array();
		$modifiedFiles = array();
		$missingFiles = array();

		foreach( explode( "\n", $output ) as $line )
		{
			list( $hash, $filename ) = explode( ":", $line );

			if( ! isset( $known[$filename] ) )
				$newFiles[$filename] = $hash;
			else
			{
				if( $known[$filename] != $hash )
					$modifiedFiles[$filename] = $hash;

				unset( $known[$filename] );
			}
		}

		foreach( $known as $filename => $hash )
			$missingFiles[$filename] = $hash;

		return array(
			'new' => $newFiles,
			'mod' => $modifiedFiles,
			'del' => $missingFiles,
		);
	} // }}}

	function collectChecksumFromSource( Instance $instance ) // {{{
	{
		$app = $instance->getApplication();

		$folder = cache_folder( $app, $this );

		$app->extractTo( $this, $folder );

		ob_start();
		include dirname(__FILE__) . "/../scripts/generate_md5_list.php";
		$content = ob_get_contents();
		ob_end_clean();

		$this->saveHashDump( $content, $app );
	} // }}}

	function collectChecksumFromInstance( Instance $instance ) // {{{
	{
		$app = $instance->getApplication();

		$access = $instance->getBestAccess( 'scripting' );
		$output = $access->runPHP( dirname(__FILE__) . '/../scripts/generate_md5_list.php', $instance->webroot );
		
		$this->saveHashDump( $output, $app );
	} // }}}

	function replicateChecksum( Version $old ) // {{{
	{
		query( "INSERT INTO file (version_id, path, hash) SELECT :new, path, hash FROM file WHERE version_id = :old",
			array( ':new' => $this->id, ':old' => $old->id ) );
	} // }}}

	function recordFile( $hash, $filename, Application $app ) // {{{
	{
		query( "INSERT INTO file (version_id, path, hash) VALUES(:version, :path, :hash)",
			array( ':version' => $this->id, ':path' => $filename, ':hash' => $hash ) );
	} // }}}

	function removeFile( $filename ) // {{{
	{
		query( "DELETE FROM file WHERE path = :p and version_id = :v",
			array( ':v' => $this->id, ':p' => $filename ) );
	} // }}}

	function replaceFile( $hash, $filename, Application $app ) // {{{
	{
		$this->removeFile( $filename );
		$this->recordFile( $hash, $filename, $app );
	} // }}}

	function getFileMap() // {{{
	{
		$map = array();
		$result = query( "SELECT path, hash FROM file WHERE version_id = :v",
			array( ':v' => $this->id ) );

		while( $row = sqlite_fetch_array( $result ) )
		{
			extract( $row );
			$map[$path] = $hash;
		}

		return $map;
	} // }}}

	private function saveHashDump( $output, Application $app ) // {{{
	{
		$entries = explode( "\n", $output );
		foreach( $entries as $line )
		{
			$parts = explode( ':', $line );
			if( count( $parts ) != 2 )
				continue;

			list( $hash, $file ) = $parts;
			$this->recordFile( $hash, $file, $app );
		}
	} // }}}
}

?>
