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
		$folder = cache_folder( $this, $version );
		$this->extractTo( $version, $folder );
		
		$pwd = trim( `pwd` );
		$tar = tempnam( TEMP_FOLDER, 'trim' );
		rename( $tar, "$tar.tar" );

		chdir( $folder );
		`tar -cf $tar.tar .`;
		`gzip -5 $tar.tar`;
		chdir( $pwd );

		$remote = $this->instance->getWorkPath( basename( "$tar.tar.gz" ) );

		$access = $this->instance->getBestAccess( 'scripting' );
		if( ! $access instanceof ShellPrompt )
			die( "Requires shell access to the server.\n" );

		$access->uploadFile( "$tar.tar.gz", $remote );
		
		$access->shellExec(
			"cd " . escapeshellarg( $this->instance->webroot ),
			"tar -zxf " . escapeshellarg( $remote ),
			"rm " . escapeshellarg( $remote ) );

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

			$entry = readline( "Branch $branch was found. If this is not correct, enter the one to use : " );
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

			$entry = readline( "Branch $branch was found. If this is not correct, enter the one to use : " );
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
				$branch = "https://tikiwiki.svn.sourceforge.net/svnroot/tikiwiki/{$version->branch}/$filename";
				$branch = str_replace( '/./', '/', $branch );
				$branch = escapeshellarg( $branch );
				`svn co $branch $folder`;
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

				$base = basename( $folder );
				`cvs -z3 -d:pserver:anonymous@tikiwiki.cvs.sourceforge.net:/cvsroot/tikiwiki co -r {$version->branch} -d $base tikiwiki 2> /dev/null`;
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

			$access->runPHP( dirname(__FILE__) . '/../../scripts/sqlupgrade.php', $this->instance->webroot );
			return;

		case 'cvs':
			$access = $this->instance->getBestAccess( 'scripting' );

			if( ! $access instanceof ShellPrompt || ! $access->hasExecutable( 'cvs' ) )
				break;

			$cvs = new CVS( 'pserver', 'anonymous', 'tikiwiki.cvs.sourceforge.net', '/cvsroot/tikiwiki', 'tikiwiki' );
			$cvs->updateInstanceTo( $this->instance, $version->branch );

			$access->runPHP( dirname(__FILE__) . '/../../scripts/sqlupgrade.php', $this->instance->webroot );
			return;
		}

		// TODO : Handle fallback
	} // }}}

	private function formatBranch( $version ) // {{{
	{
		if( substr( $version, 0, 4 ) == '1.9.' )
			return "REL-" . str_replace( '.', '-', $version );
		else
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
}

?>
