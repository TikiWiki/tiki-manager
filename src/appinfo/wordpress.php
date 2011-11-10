<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class Application_Wordpress extends Application
{
	const BASE = "https://core.svn.wordpress.org/"; 
	const REFERENCE_FILE = "wp-settings.php";
	
	private $installType = null;
	private $branch = null;
	private $installed = null;
	
	function getName()
	{
		return 'wordpress';
	}

	function getVersions()
	{
		$versions = array();

		$base = self::BASE;
		foreach (explode("\n", `svn ls $base/tags`) as $line) {
			$line = trim($line);
			if (empty($line)) {
				continue;
			}

			if (substr($line, -1) == '/' && ctype_digit($line{0})) {
				$versions[] = Version::buildFake('svn', "tags/" . substr($line, 0, -1));
			}
		}

		foreach (explode("\n", `svn ls $base/branches`) as $line) {
			$line = trim($line);
			if (empty($line)) {
				continue;
			}

			if (substr($line, -1) == '/' && ctype_digit($line{0})) {
				$versions[] = Version::buildFake('svn', "branches/" . substr($line, 0, -1));
			}
		}

		$versions[] = Version::buildFake('svn', "trunk");

		return $versions;
	}

	function isInstalled()
	{
		if (!is_null($this->installed)) {
			return $this->installed;
		}

		$access = $this->instance->getBestAccess('filetransfer');

		return $this->installed = $access->fileExists($this->instance->getWebPath(self::REFERENCE_FILE));
	}

	function getInstallType()
	{
		if (!is_null($this->installType)) {
			return $this->installType;
		}

		$access = $this->instance->getBestAccess('filetransfer');
		if ($access->fileExists( $this->instance->getWebPath('.svn/entries'))) {
			return $this->installType = 'svn';
		} else {
			return $this->installType = 'tarball';
		}
	}

	function install(Version $version)
	{
		$access = $this->instance->getBestAccess('scripting');
		if ($access instanceof ShellPrompt) {
			$access->shellExec(
				$this->getExtractCommand($version, $this->instance->webroot)
			);
		} else {
			$folder = cache_folder($this, $version);
			$this->extractTo($version, $folder);

			$access->copyLocalFolder($folder);
		}

		$this->branch = $version->branch;
		$this->installType = $version->type;
		$this->installed = true;

		$version = $this->registerCurrentInstallation();
		$version->collectChecksumFromSource($this->instance);
	}

	function getBranch()
	{
		if ($this->branch) {
			return $this->branch;
		}

		if ($this->getInstallType() == 'svn') {
			$svn = new RC_SVN(self::BASE);
			if ($branch = $svn->getRepositoryBranch($this->instance)) {
				info("Detected SVN : $branch");
				return $this->branch = $branch;
			}
		}

		$branch = '';
		while (empty($branch)) {
			$branch = readline("No version found. Which tag should be used? (Ex.: (CVS) REL-1-9-10 or (Subversion) branches/1.10) ");
		}

		return $this->branch = $branch;
	}

	function getUpdateDate()
	{
		$access = $this->instance->getBestAccess('filetransfer');

		$date = $access->fileModificationDate($this->instance->getWebPath(self::REFERENCE_FILE));

		return $date;
	}

	function getSourceFile(Version $version, $filename)
	{
		$dot = strrpos($filename, '.');
		$ext = substr($filename, $dot);

		$local = tempnam(TEMP_FOLDER, 'trim');
		rename($local, $local . $ext);
		$local .= $ext;

		if ($version->type == 'svn') {
			$branch = self::BASE . "{$version->branch}/$filename";
			$branch = str_replace('/./', '/', $branch);
			$branch = escapeshellarg($branch);
			`svn export $branch $local`;

			return $local;
		}
	}

	private function getExtractCommand($version, $folder)
	{
		if ($version->type == 'svn' || $version->type == 'tarball')	{
			$branch = self::BASE . "{$version->branch}";
			$branch = str_replace('/./', '/', $branch);
			$branch = escapeshellarg($branch);
			return "svn co $branch $folder";
		}
	}

	function extractTo(Version $version, $folder)
	{
		if ($version->type == 'svn' || $version->type == 'tarball') {
			if(file_exists($folder)) {
				`svn up --non-interactive $folder`;
			} else {
				$command = $this->getExtractCommand($version, $folder);
				`$command`;
			}
		}
	}

	function performActualUpdate(Version $version)
	{
		switch($this->getInstallType())	{
			case 'svn':
			case 'tarball':
				$access = $this->instance->getBestAccess('scripting');
	
				if ($access instanceof ShellPrompt && $access->hasExecutable('svn')) {
					$svn = new RC_SVN(self::BASE);
					$svn->updateInstanceTo($this->instance, $version->branch);
				} elseif($access instanceof Mountable) {
					$folder = cache_folder($this, $version);
					$this->extractTo($version, $folder);
	
					$access->copyLocalFolder($folder);
				}
	
				return;
	
		}

		// TODO : Handle fallback
	}

	function getFileLocations()
	{
		$access = $this->instance->getBestAccess('scripting');
		$folders = array($this->instance->webroot);
		
		return $folders;
	}

	function requiresDatabase()
	{
		return true;
	}

	function getAcceptableExtensions()
	{
		return array('mysqli', 'mysql');
	}

	function setupDatabase(Database $database)
	{
		die('Not implemented yet.');
	}

	function restoreDatabase(Database $database, $remoteFile)
	{
		die('Not implemented yet.');
	}

	function backupDatabase($target)
	{
		$access = $this->instance->getBestAccess('scripting');
		if ($access instanceof ShellPrompt) {
			$randomName = md5(time() . 'trimbackup') . '.sql.gz';
			$remoteFile = $this->instance->getWorkPath($randomName);
			$access->runPHP(dirname(__FILE__) . '/../../scripts/wordpress/backup_database.php', array($this->instance->webroot, $remoteFile));
			$localName = $access->downloadFile( $remoteFile );
			$access->deleteFile( $remoteFile );

			`zcat $localName > $target`;
			unlink( $localName );
		} else {
			die('To backup a Wordpress instance you need to use SSH.');
		}
	}

	function beforeChecksumCollect()
	{
		//TODO
		return;
	}
}