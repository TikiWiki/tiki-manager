<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class Application_Tiki extends Application
{
	private $installType = null;
	private $branch = null;
	private $installed = null;

	function getName()
	{
		return 'tiki';
	}

	function getVersions() // {{{
	{
		$versions = array();
		$versions[] = Version::buildFake( 'cvs', 'REL-1-9-11' );
		$versions[] = Version::buildFake( 'cvs', 'BRANCH-1-9' );

		$base = "https://svn.code.sf.net/p/tikiwiki/code";
		foreach( explode( "\n", `svn ls $base/tags` ) as $line )
		{
			$line = trim( $line );
			if( empty( $line ) )
				continue;

			if( substr( $line, -1 ) == '/' && ctype_digit( $line{0} ) )
				$versions[] = Version::buildFake( 'svn', "tags/" . substr( $line, 0, -1 ) );
		}

		foreach( explode( "\n", `svn ls $base/branches` ) as $line )
		{
			$line = trim( $line );
			if( empty( $line ) )
				continue;

			if( substr( $line, -1 ) == '/' && ctype_digit( $line{0} ) )
				$versions[] = Version::buildFake( 'svn', "branches/" . substr( $line, 0, -1 ) );
		}

		$versions[] = Version::buildFake( 'svn', "trunk" );

		return $versions;
	} // }}}

	function isInstalled() // {{{
	{
		if( ! is_null( $this->installed ) )
			return $this->installed;

		$access = $this->instance->getBestAccess( 'filetransfer' );

		return $this->installed = $access->fileExists( $this->instance->getWebPath( 'tiki-setup.php' ) );
	} // }}}

	function getInstallType() // {{{
	{
		if( ! is_null( $this->installType ) )
			return $this->installType;

		$access = $this->instance->getBestAccess( 'filetransfer' );
		if( $access->fileExists( $this->instance->getWebPath( 'CVS' ) ) )
			return $this->installType = 'cvs';
		elseif( $access->fileExists( $this->instance->getWebPath( '.svn/entries' ) ) )
			return $this->installType = 'svn';
		else
			return $this->installType = 'tarball';
	} // }}}

	function install( Version $version ) // {{{
	{
		$access = $this->instance->getBestAccess( 'scripting' );
		if( $access instanceof ShellPrompt ) {
			$access->shellExec(
				$this->getExtractCommand( $version, $this->instance->webroot ) );
		} else {
			$folder = cache_folder( $this, $version );
			$this->extractTo( $version, $folder );

			$access->copyLocalFolder( $folder );
		}

		$this->branch = $version->branch;
		$this->installType = $version->type;
		$this->installed = true;

		$version = $this->registerCurrentInstallation();
		$version->collectChecksumFromSource( $this->instance );

		$this->fixPermissions();

		if( ! $access->fileExists( $this->instance->getWebPath('.htaccess') ) )
			$access->moveFile( 
				$this->instance->getWebPath('_htaccess'),
				$this->instance->getWebPath('.htaccess')
			);

		$access->shellExec('touch ' . escapeshellarg($this->instance->getWebPath('db/lock')));
	} // }}}

	function getBranch() // {{{
	{
		if( $this->branch )
			return $this->branch;

		if( $this->getInstallType() == 'svn' )
		{
			$svn = new RC_SVN( "https://svn.code.sf.net/p/tikiwiki/code" );
			if( $branch = $svn->getRepositoryBranch( $this->instance ) )
			{
				info( "Detected SVN : $branch" );
				return $this->branch = $branch;
			}
		}

		$access = $this->instance->getBestAccess( 'filetransfer' );

		$content = $access->fileGetContents( $this->instance->getWebPath( 'tiki-setup.php' ) );

		if( preg_match( "/tiki_version\s*=\s*[\"'](\d+\.\d+\.\d+(\.\d+)?)/", $content, $parts ) )
		{
			$version = $parts[1];
			$branch = $this->formatBranch( $version );

			echo "The branch provided may not be correct. Until 1.10 is tagged, use branches/1.10.\n";
			$entry = readline( "If this is not correct, enter the one to use: [$branch] " );
			if( !empty( $entry ) )
				return $this->branch = $entry;
			else
				return $this->branch = $branch;
		}

		$content = $access->fileGetContents( $this->instance->getWebPath( 'lib/setup/twversion.class.php' ) );
		if( empty( $content ) )
			$content = $access->fileGetContents( $this->instance->getWebPath( 'lib/twversion.class.php' ) );

		if( preg_match( "/this-\>version\s*=\s*[\"'](\d+\.\d+(\.\d+)?(\.\d+)?(\w+)?)/", $content, $parts ) )
		{
			$version = $parts[1];
			$branch = $this->formatBranch( $version );

			if( strpos( $branch, 'branches/1.10' ) === 0 )
				$branch = 'branches/2.0';

			$entry = readline( "If this is not correct, enter the one to use: [$branch] " );
			if( !empty( $entry ) )
				return $this->branch = $entry;
		}
		else
		{
			$branch = '';
			while( empty( $branch ) )
				$branch = readline( "No version found. Which tag should be used? (Ex.: (CVS) REL-1-9-10 or (Subversion) branches/1.10) " );
		}

		return $this->branch = $branch;
	} // }}}

	function getUpdateDate() // {{{
	{
		$access = $this->instance->getBestAccess( 'filetransfer' );

		$date = $access->fileModificationDate( $this->instance->getWebPath( 'tiki-setup.php' ) );

		return $date;
	} // }}}

	function getSourceFile( Version $version, $filename ) // {{{
	{
		$dot = strrpos( $filename, '.' );
		$ext = substr( $filename, $dot );

		$local = tempnam( TEMP_FOLDER, 'trim' );
		rename( $local, $local . $ext );
		$local .= $ext;

		if( $version->type == 'svn' )
		{
			$branch = "https://svn.code.sf.net/p/tikiwiki/code/{$version->branch}/$filename";
			$branch = str_replace( '/./', '/', $branch );
			$branch = escapeshellarg( $branch );
			`svn export $branch $local`;

			return $local;
		}
		elseif( $version->type == 'cvs' )
		{
			$cur = `pwd`;
			chdir( TEMP_FOLDER );

			$folder = md5( $filename );
			mkdir( $folder );
			$path = "/$filename";
			$path = str_replace( '/./', '/', $path );
			$epath = escapeshellarg( $path );

			`cvs -z3 -d:pserver:anonymous@tikiwiki.cvs.sourceforge.net:/cvsroot/tikiwiki co -r {$version->branch} -d $folder tikiwiki$epath 2> /dev/null`;

			rename( "$folder$path", $local );

			`rm -Rf $folder`;
			chdir( trim($cur) );
			
			return $local;
		}
	} // }}}

	private function getExtractCommand( $version, $folder ) // {{{
	{
		if( $version->type == 'svn' || $version->type == 'tarball' )
		{
			$branch = "https://svn.code.sf.net/p/tikiwiki/code/{$version->branch}";
			$branch = str_replace( '/./', '/', $branch );
			$branch = escapeshellarg( $branch );
			return "svn co $branch $folder";
		}
		elseif( $version->type == 'cvs' )
		{
			$base = basename( $folder );
			return "cd $folder; cd ..;cvs -z3 -d:pserver:anonymous@tikiwiki.cvs.sourceforge.net:/cvsroot/tikiwiki co -r {$version->branch} -d $base tikiwiki 2> /dev/null";
		}
	} // }}}

	function extractTo( Version $version, $folder ) // {{{
	{
		if( $version->type == 'svn' || $version->type == 'tarball' )
		{
			if( file_exists( $folder ) )
			{
				`svn up --non-interactive $folder`;
			}
			else
			{
				$command = $this->getExtractCommand( $version, $folder );
				`$command`;
			}
		}
		elseif( $version->type == 'cvs' )
		{
			$cur = `pwd`;

			if( file_exists( $folder ) )
			{
				chdir( $folder );
				`cvs up -d`;
			}
			else
			{
				chdir( $folder );
				$command = $this->getExtractCommand( $version, $folder );
				`$command`;
			}

			chdir( trim($cur) );
		}
	} // }}}

	function performActualUpdate( Version $version ) // {{{
	{
		switch( $this->getInstallType() )
		{
		case 'svn':
		case 'tarball':
			$access = $this->instance->getBestAccess( 'scripting' );

			if( $access instanceof ShellPrompt && $access->hasExecutable( 'svn' ) )
			{
				$access->shellExec(
					"rm -Rf " . escapeshellarg( $this->instance->getWebPath( 'temp/cache' ) )
				);

				$svn = new RC_SVN( "https://svn.code.sf.net/p/tikiwiki/code" );
				$svn->updateInstanceTo( $this->instance, $version->branch );
				$access->shellExec( "chmod 0777 temp temp/cache" );
			} elseif( $access instanceof Mountable ) {
				$folder = cache_folder( $this, $version );
				$this->extractTo( $version, $folder );

				$access->copyLocalFolder( $folder );
			}

			info( "Updating database schema." );
			$access->runPHP( dirname(__FILE__) . '/../../scripts/tiki/sqlupgrade.php', array( $this->instance->webroot ) );

			info( "Fixing permissions." );
			$this->fixPermissions();
			$access->shellExec('touch ' . escapeshellarg($this->instance->getWebPath('db/lock')));
			return;

		case 'cvs':
			$access = $this->instance->getBestAccess( 'scripting' );

			if( ! $access instanceof ShellPrompt || ! $access->hasExecutable( 'cvs' ) )
				break;

			$cvs = new RC_CVS( 'pserver', 'anonymous', 'tikiwiki.cvs.sourceforge.net', '/cvsroot/tikiwiki', 'tikiwiki' );
			$cvs->updateInstanceTo( $this->instance, $version->branch );

			info( "Updating database schema." );
			$access->runPHP( dirname(__FILE__) . '/../../scripts/tiki/sqlupgrade.php', array( $this->instance->webroot ) );

			info( "Fixing permissions." );
			$this->fixPermissions();
			$access->shellExec('touch ' . escapeshellarg($this->instance->getWebPath('db/lock')));
			return;
		}

		// TODO : Handle fallback
	} // }}}

	private function formatBranch( $version ) // {{{
	{
		if( substr( $version, 0, 4 ) == '1.9.' )
			return "REL-" . str_replace( '.', '-', $version );
		elseif( $this->getInstallType() == 'cvs' )
			return "BRANCH-1-10";
		elseif( $this->getInstallType() == 'svn' )
			return "tags/$version";
		elseif( $this->getInstallType() == 'tarball' )
			return "tags/$version";
	} // }}}

	function fixPermissions() // {{{
	{
		$access = $this->instance->getBestAccess( 'scripting' );

		if( $access instanceof ShellPrompt ) {
			$access->chdir( $this->instance->webroot );

			if ($this->instance->hasComposer()) {
				$ret = $access->shellExec("bash setup.sh -n fix 2> /dev/null");    // does composer as well
				// echo $ret; TODO output if verbose one day, or log it?
			} else {
				$filename = $this->instance->getWorkPath( 'setup.sh' );
				$access->uploadFile( dirname(__FILE__) . '/../../scripts/setup.sh', $filename );
				$access->shellExec(
					"bash " . escapeshellarg( $filename ) . " 2> /dev/null" );
			}
		} elseif( $access instanceof Mountable ) {
			return;
			$target = MOUNT_FOLDER . $this->instance->webroot;
			$cwd = getcwd();

			$access->mount( MOUNT_FOLDER );
			chdir( $target );

			// This code was taken from the installer.
			$dirs=array(
					'backups',
					'db',
					'dump',
					'img/wiki',
					'img/wiki_up',
					'img/trackers',
					'modules/cache',
					'temp',
					'temp/cache',
					'templates_c',
					'templates',
					'styles',
					'whelp');

			$ret = "";
			foreach ($dirs as $dir) {
				$dir = $dir;
				// Create directories as needed
				if (!is_dir($dir)) {
					mkdir($dir,02775);
				}

				chmod($dir,02775);
			}

			$access->umount();

			chdir( $cwd );
		}
	} // }}}

	function getFileLocations() // {{{
	{
		$access = $this->instance->getBestAccess( 'scripting' );
		$out = $access->runPHP( dirname(__FILE__) . '/../../scripts/tiki/get_directory_list.php', array( $this->instance->webroot ) );

		$folders = array( $this->instance->webroot );
		foreach( explode( "\n", $out ) as $line )
		{
			$line = trim( $line );
			if( empty( $line ) )
				continue;

			$line = rtrim( $line, '/' );

			if( $line{0} != '/' )
				$line = "{$this->instance->webroot}/$line";

			if( ! empty( $line ) )
				$folders[] = $line;
		}

		return $folders;
	} // }}}

	function requiresDatabase() // {{{
	{
		return true;
	} // }}}

	function getAcceptableExtensions() // {{{
	{
		return array( 'mysqli', 'mysql' );
	} // }}}

	function setupDatabase( Database $database ) // {{{
	{
		$tmp = tempnam( TEMP_FOLDER, 'dblocal' );
		file_put_contents( $tmp, <<<LOCAL
<?php
\$db_tiki='{$database->type}';
\$host_tiki='{$database->host}';
\$user_tiki='{$database->user}';
\$pass_tiki='{$database->pass}';
\$dbs_tiki='{$database->dbname}';
\$client_charset = 'utf8';
?>
LOCAL
);

		$access = $this->instance->getBestAccess( 'filetransfer' );
		$access->uploadFile( $tmp, 'db/local.php' );

		if( $access->fileExists( 'installer/shell.php' ) )
		{
			if( $access instanceof ShellPrompt ) {
				$access = $this->instance->getBestAccess( 'scripting' );
				$access->chdir( $this->instance->webroot );
				$access->shellExec( $this->instance->phpexec . ' installer/shell.php install' );
			} else {
				$access->runPHP( dirname(__FILE__) . '/../../scripts/tiki/tiki_dbinstall_ftp.php', array( $this->instance->webroot ) );
			}
		}
		else
		{
			// FIXME : Not FTP compatible ? prior to 3.0 only
			$access = $this->instance->getBestAccess( 'scripting' );
			$file = $this->instance->getWebPath( 'db/tiki.sql' );
			$root = $this->instance->webroot;
			$access->runPHP( dirname(__FILE__) . '/../../scripts/tiki/run_sql_file.php', array( $root, $file ) );
			$access->runPHP( dirname(__FILE__) . '/../../scripts/tiki/sqlupgrade.php', array( $this->instance->webroot ) );
		}
	} // }}}

	function restoreDatabase( Database $database, $remoteFile ) // {{{
	{
		$tmp = tempnam( TEMP_FOLDER, 'dblocal' );
		file_put_contents( $tmp, <<<LOCAL
<?php
\$db_tiki='{$database->type}';
\$dbversion_tiki='2.0';
\$host_tiki='{$database->host}';
\$user_tiki='{$database->user}';
\$pass_tiki='{$database->pass}';
\$dbs_tiki='{$database->dbname}';
?>
LOCAL
);

		$access = $this->instance->getBestAccess( 'filetransfer' );
		$access->uploadFile( $tmp, 'db/local.php' );

		$access = $this->instance->getBestAccess( 'scripting' );
		$root = $this->instance->webroot;
		// FIXME : Not FTP compatible (arguments)
		$access->runPHP( dirname(__FILE__) . '/../../scripts/tiki/run_sql_file.php', array( $root, $remoteFile ) );
	} // }}}

	function backupDatabase( $target ) // {{{
	{
		$access = $this->instance->getBestAccess( 'scripting' );
		if( $access instanceof ShellPrompt ) {
			$randomName = md5( time() . 'trimbackup' ) . '.sql.gz';
			$remoteFile = $this->instance->getWorkPath( $randomName );
			$access->runPHP( dirname(__FILE__) . '/../../scripts/tiki/backup_database.php', array( $this->instance->webroot, $remoteFile ) );
			$localName = $access->downloadFile( $remoteFile );
			$access->deleteFile( $remoteFile );

			`zcat $localName > $target`;
			unlink( $localName );
		} else {
			$data = $access->runPHP( dirname(__FILE__) . '/../../tiki/scripts/mysqldump.php' );
			file_put_contents( $target, $data );
		}
	} // }}}

	function beforeChecksumCollect() // {{{
	{
		$path = $this->instance->getWebPath( 'templates_c/*[!index].php' );

		// FIXME : Not FTP compatible
		if( ( $access = $this->instance->getBestAccess('scripting') ) instanceof ShellPrompt ) {
			$access->shellExec( "rm " . $path );
		}
	} // }}}

	function installProfile( $domain, $profile ) {
		$access = $this->instance->getBestAccess('scripting');

		echo $access->runPHP( dirname(__FILE__) . '/../../scripts/tiki/remote_install_profile.php', array( $this->instance->webroot, $domain, $profile ) );
	}
}
