<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class Application_Tikiwiki extends Application
{
	private $installType = null;

	function getName()
	{
		return 'tikiwiki';
	}

	function isInstalled() // {{{
	{
		static $installed = null;
		if( ! is_null( $installed ) )
			return $installed;

		$access = $this->instance->getBestAccess( 'filetransfer' );

		return $installed = $access->fileExists( $this->instance->getWebPath( 'tiki-setup.php' ) );
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

	function getBranch() // {{{
	{
		$access = $this->instance->getBestAccess( 'filetransfer' );

		$content = $access->fileGetContents( $this->instance->getWebPath( 'tiki-setup.php' ) );

		if( preg_match( "/tiki_version\s*=\s*[\"'](\d+\.\d+\.\d+(\.\d+)?)/", $content, $parts ) )
		{
			$version = $parts[1];
			$branch = $this->formatBranch( $version );

			$entry = readline( "Branch $branch was found. If this is not correct, enter the one to use : " );
			if( !empty( $entry ) )
				return $entry;
			else
				return $branch;
		}

		$content = $access->fileGetContents( $this->instance->getWebPath( 'lib/setup/twversion.class.php' ) );

		if( preg_match( "/this-\>version\s*=\s*[\"'](\d+\.\d+\.\d+(\.\d+)?)/", $content, $parts ) )
		{
			$version = $parts[1];
			$branch = $this->formatBranch( $version );

			$entry = readline( "Branch $branch was found. If this is not correct, enter the one to use : " );
			if( !empty( $entry ) )
				return $entry;
		}
		else
		{
			$branch = '';
			while( empty( $branch ) )
				$branch = readline( "No version found. Which tag should be used? (Ex.: (CVS) REL-1-9-10 or (Subversion) branches/1.10) " );
		}

		return $branch;
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

		$local = tempnam( '/tmp', 'trim' );
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
			chdir( '/tmp' );

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

			return;

		case 'cvs':
			$access = $this->instance->getBestAccess( 'scripting' );

			if( ! $access instanceof ShellPrompt || ! $access->hasExecutable( 'cvs' ) )
				break;

			$cvs = new CVS( 'pserver', 'anonymous', 'tikiwiki.cvs.sourceforge.net', '/cvsroot/tikiwiki', 'tikiwiki' );
			$cvs->updateInstanceTo( $this->instance, $version->branch );
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
}

?>
