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
		while( $instance = $result->fetchObject( 'Instance' ) )
		{
			$instances[] = $instance;
		}

		return $instances;
	} // }}}

	function getInstance( $id ) // {{{
	{
		$result = query( "
			SELECT instance_id id, name, contact, webroot, weburl, tempdir, phpexec, app 
			FROM instance
			WHERE instance_id = :id", array( ':id' => $id ) );

		$instance = $result->fetchObject( 'Instance' );

		return $instance;
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
			WHERE type = 'cvs' OR type = 'svn' OR type = 'tarball'" );

		$instances = array();
		while( $instance = $result->fetchObject( 'Instance' ) )
		{
			$instances[] = $instance;
		}

		return $instances;
	} // }}}

	function getRestorableInstances() // {{{
	{
		$dp = opendir( BACKUP_FOLDER );

		$backups = array();
		while( false !== $file = readdir( $dp ) )
		{
			if( ! is_numeric( $file ) )
				continue;

			if( $instance = self::getInstance( $file ) )
				$backups[] = $instance;
		}

		closedir( $dp );

		return $backups;
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
		query( "DELETE FROM file WHERE version_id IN( SELECT version_id FROM version WHERE instance_id = :id )", array( ':id' => $this->id ) );
		query( "DELETE FROM version WHERE instance_id = :id", array( ':id' => $this->id ) );
		query( "DELETE FROM access WHERE instance_id = :id", array( ':id' => $this->id ) );
		query( "DELETE FROM backup WHERE instance_id = :id", array( ':id' => $this->id ) );
		query( "DELETE FROM instance WHERE instance_id = :id", array( ':id' => $this->id ) );
		query( "DELETE FROM access WHERE instance_id = :id", array( ':id' => $this->id ) );
		query( "DELETE FROM file WHERE version_id IN(SELECT version_id FROM version WHERE instance_id = :id)", array( ':id' => $this->id ) );
		query( "DELETE FROM version WHERE instance_id = :id", array( ':id' => $this->id ) );
		query( "DELETE FROM report_receiver WHERE instance_id = :id", array( ':id' => $this->id ) );
		query( "DELETE FROM report_content WHERE instance_id = :id OR receiver_id = :id", array( ':id' => $this->id ) );
	} // }}}

	function registerAccessMethod( $type, $host, $user, $password = null, $port = null ) // {{{
	{
		if( ! $class = Access::getClassFor( $type ) ) {
			return;
		}

		$access = new $class( $this );
		$access->host = $host;
		$access->user = $user;
		$access->password = $password;

		if ($port) {
			$access->port = $port;
		}

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

	function getWebUrl( $relativePath ) // {{{
	{
		$weburl = rtrim( $this->weburl, '/' );
		
		$path = "$weburl/$relativePath";
		$path = str_replace( '/./', '/', $path );

		return $path;
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

	function getExtensions() // {{{
	{
		$access = $this->getBestAccess( 'scripting' );
		$content = $access->runPHP( dirname(__FILE__) . '/../scripts/get_extensions.php' );
		$modules = explode( "\n", $content );

		return $modules;
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

		$object = $result->fetchObject( 'Version' );

		return $object;
	} // }}}

	function hasComposer() {
		$current = $this->getLatestVersion();
		preg_match('/(\d+)\.?/', $current->branch, $m);
		if ($m) {
			return (float) $m[1] >= 11;
		} else {
			return false;
		}
	}

	function getApplication() // {{{
	{
		if( empty( $this->app ) )
			return false;

		$class = 'Application_' . ucfirst( $this->app );

		$dir = dirname(__FILE__) . "/appinfo";
		if( ! class_exists( $class ) )
			require_once "$dir/{$this->app}.php";

		return new $class( $this );
	} // }}}

	function backup() // {{{
	{
		$access = $this->getBestAccess( 'scripting' );
		$locations = $this->getApplication()->getFileLocations();
		$locations = array_merge( $locations, $this->getExtraBackups() );

		$approot = BACKUP_FOLDER . '/' . $this->id;
		if( ! file_exists( $approot ) )
			mkdir( $approot );

		// Bring all remote files locally
		`rm $approot/manifest.txt`;
		info( "Downloading files locally." );
		foreach( $locations as $remote )
		{
			$hash = md5( $remote );
			$locmirror = $approot . '/' . $hash;
			$access->localizeFolder( $remote, $locmirror );
			`echo "$hash    $remote" >> $approot/manifest.txt`;
		}

		$target = $approot . '/database_dump.sql';
		info( "Obtaining database dump." );
		$this->getApplication()->backupDatabase( $target );

		// Perform archiving
		$current = getcwd();
		chdir( BACKUP_FOLDER );

		info( "Creating archive." );
		$tarLocation = ARCHIVE_FOLDER . "/{$this->id}_" . date( 'Y-m-d_H:i:s' ) . '.tar';
		$tar = escapeshellarg( $tarLocation );
		`tar -cf $tar {$this->id}`;
		`bzip2 -6 $tar`;

		chdir( $current );
	} // }}}

	function getExtraBackups() // {{{
	{
		$result = query( "SELECT location FROM backup WHERE instance_id = :id",
			array( ':id' => $this->id ) );

		$list = array();
		while( $str = $result->fetchColumn() )
			$list[] = $str;

		return $list;
	} // }}}

	function setExtraBackups( $paths ) // {{{
	{
		query( "DELETE FROM backup WHERE instance_id = :id",
			array( ':id' => $this->id ) );

		foreach( $paths as $path )
			if( ! empty( $path ) )
				query( "INSERT INTO backup ( instance_id, location ) VALUES( :id, :loc )",
					array( ':id' => $this->id, ':loc' => $path ) );
	} // }}}

	function getArchives() // {{{
	{
		return array_reverse( glob( ARCHIVE_FOLDER . "/{$this->id}_*.tar.bz2" ) );
	} // }}}

	function lock() // {{{
	{
		$access = $this->getBestAccess( 'scripting' );

		info( "Locking website." );
		if( ! $access->fileExists( $this->getWebPath( 'maintenance.html' ) ) )
			$access->uploadFile( dirname(__FILE__) . "/../scripts/maintenance.html", "maintenance.html" );

		if( $access->fileExists( $this->getWebPath( '.htaccess' ) ) )
			$access->moveFile( '.htaccess', '.htaccess.bak' );

		$access->uploadFile( dirname(__FILE__) . "/../scripts/maintenance.htaccess", ".htaccess" );

		return true;
	} // }}}

	function unlock() // {{{
	{
		info( "Unlocking website." );
		$access = $this->getBestAccess( 'scripting' );
		$access->deleteFile( '.htaccess' );

		if( $access->fileExists( '.htaccess.bak' ) )
			$access->moveFile( '.htaccess.bak', '.htaccess' );
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

	public static function buildFake( $type, $branch ) // {{{{
	{
		$v = new self;
		$v->type = $type;
		$v->branch = $branch;
		$v->date = date( 'Y-m-d' );

		return $v;
	} // }}}

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

		return $result->fetchColumn() > 0;
	} // }}}

	function performCheck( Instance $instance ) // {{{
	{
		$app = $instance->getApplication();
		$app->beforeChecksumCollect();

		$access = $instance->getBestAccess( 'scripting' );
		$output = $access->runPHP( dirname(__FILE__) . '/../scripts/generate_md5_list.php', array( $instance->webroot ) );
		
		$known = $this->getFileMap();

		$newFiles = array();
		$modifiedFiles = array();
		$missingFiles = array();

		foreach( explode( "\n", $output ) as $line )
		{
			if( empty( $line ) )
				continue;

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
		$app->beforeChecksumCollect();

		$access = $instance->getBestAccess( 'scripting' );
		$output = $access->runPHP( dirname(__FILE__) . '/../scripts/generate_md5_list.php', array( $instance->webroot ) );
		
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

		while( $row = $result->fetch() )
		{
			extract( $row );
			$map[$path] = $hash;
		}

		return $map;
	} // }}}

	private function saveHashDump( $output, Application $app ) // {{{
	{
		$entries = explode( "\n", $output );
		query('BEGIN TRANSACTION');
		foreach( $entries as $line )
		{
			$parts = explode( ':', $line );
			if( count( $parts ) != 2 )
				continue;

			list( $hash, $file ) = $parts;
			$this->recordFile( $hash, $file, $app );
		}
		query('COMMIT');
	} // }}}
}
