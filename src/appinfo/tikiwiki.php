<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class Application_Tikiwiki extends Application
{
	private $installType = null;
	private $branch = null;
	private $installed = null;

	function getName()
	{
		return 'tikiwiki';
	}

	function getVersions() // {{{
	{
		$versions = array();
		$versions[] = Version::buildFake( 'cvs', 'REL-1-9-11' );
		$versions[] = Version::buildFake( 'cvs', 'BRANCH-1-9' );

		$base = "https://tikiwiki.svn.sourceforge.net/svnroot/tikiwiki";
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
		if( ! $access instanceof ShellPrompt )
			die( "Requires shell access to the server.\n" );

		$access->shellExec(
			"cd " . escapeshellarg( $this->instance->webroot ),
			$this->getExtractCommand( $version, $this->instance->webroot ),
			"cp _htaccess .htaccess" );

		$this->branch = $version->branch;
		$this->installType = $version->type;
		$this->installed = true;

		$version = $this->registerCurrentInstallation();
		$version->collectChecksumFromSource( $this->instance );

		$this->fixPermissions();
	} // }}}

	function getBranch() // {{{
	{
		if( $this->branch )
			return $this->branch;

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

		if( preg_match( "/this-\>version\s*=\s*[\"'](\d+\.\d+\.\d+(\.\d+)?)/", $content, $parts ) )
		{
			$version = $parts[1];
			$branch = $this->formatBranch( $version );

			echo "The branch provided may not be correct. Until 1.10 is tagged, use branches/1.10.\n";
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
			$branch = "https://tikiwiki.svn.sourceforge.net/svnroot/tikiwiki/{$version->branch}/$filename";
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
		if( $version->type == 'svn' )
		{
			$branch = "https://tikiwiki.svn.sourceforge.net/svnroot/tikiwiki/{$version->branch}";
			$branch = str_replace( '/./', '/', $branch );
			$branch = escapeshellarg( $branch );
			return "svn co $branch $folder";
		}
		elseif( $version->type == 'cvs' )
		{
			$base = basename( $folder );
			return "cvs -z3 -d:pserver:anonymous@tikiwiki.cvs.sourceforge.net:/cvsroot/tikiwiki co -r {$version->branch} -d $base tikiwiki 2> /dev/null";
		}
	} // }}}

	function extractTo( Version $version, $folder ) // {{{
	{
		if( $version->type == 'svn' )
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
				chdir( dirname( $folder ) );

				$command = $this->getExtractCommand( $version );
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
			$access = $this->instance->getBestAccess( 'scripting' );

			if( ! $access instanceof ShellPrompt || ! $access->hasExecutable( 'svn' ) )
				break;

			$svn = new SVN( "https://tikiwiki.svn.sourceforge.net/svnroot/tikiwiki" );
			$svn->updateInstanceTo( $this->instance, $version->branch );

			info( "Updating database schema." );
			$access->runPHP( dirname(__FILE__) . '/../../scripts/sqlupgrade.php', $this->instance->webroot );
			return;

		case 'cvs':
			$access = $this->instance->getBestAccess( 'scripting' );

			if( ! $access instanceof ShellPrompt || ! $access->hasExecutable( 'cvs' ) )
				break;

			$cvs = new CVS( 'pserver', 'anonymous', 'tikiwiki.cvs.sourceforge.net', '/cvsroot/tikiwiki', 'tikiwiki' );
			$cvs->updateInstanceTo( $this->instance, $version->branch );

			info( "Updating database schema." );
			$access->runPHP( dirname(__FILE__) . '/../../scripts/sqlupgrade.php', $this->instance->webroot );
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
	} // }}}

	function fixPermissions() // {{{
	{
		$access = $this->instance->getBestAccess( 'scripting' );

		$filename = $this->instance->getWorkPath( 'setup.sh' );
		$access->uploadFile( dirname(__FILE__) . '/../../scripts/setup.sh', $filename );
		$access->shellExec(
			"cd " . escapeshellarg( $this->instance->webroot ),
			"bash " . escapeshellarg( $filename ) );
	} // }}}

	function getFileLocations() // {{{
	{
		$access = $this->instance->getBestAccess( 'scripting' );
		$out = $access->runPHP( dirname(__FILE__) . '/../../scripts/get_directory_list.php', $this->instance->webroot );

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
\$dbversion_tiki='1.10';
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
		$file = $this->instance->getWebPath( 'db/tiki.sql' );
		$root = $this->instance->webroot;
		$access->runPHP( dirname(__FILE__) . '/../../scripts/run_sql_file.php', escapeshellarg( $root ) . ' ' . escapeshellarg( $file ) );
	} // }}}
}

?>
