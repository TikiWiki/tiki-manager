<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

define('SVN_TIKIWIKI_URI', getenv('SVN_TIKIWIKI_URI') ?: 'https://svn.code.sf.net/p/tikiwiki/code');

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

        $base = SVN_TIKIWIKI_URI;
        $versionsTemp = array();
        foreach (explode("\n", `svn ls $base/tags`) as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (substr($line, -1) == '/' && ctype_digit($line{0}))
                $versionsTemp[] = 'svn:tags/' . substr($line, 0, -1);
        }
        sort($versionsTemp, SORT_NATURAL);
        $versions = array_merge($versions, $versionsTemp);

        $versionsTemp = array();
        foreach (explode("\n", `svn ls $base/branches`) as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (substr($line, -1) == '/' && ctype_digit($line{0}))
                $versionsTemp[] = 'svn:branches/' . substr($line, 0, -1);
        }
        sort($versionsTemp, SORT_NATURAL);
        $versions = array_merge($versions, $versionsTemp);

        // Trunk as last option
        $versions[] = 'svn:trunk';

        $versions_sorted = array();
        foreach ($versions as $version) {
            list($type, $branch) = explode(':', $version);
            $versions_sorted[] = Version::buildFake($type, $branch);
        }

        return $versions_sorted;
    } // }}}

    function isInstalled() // {{{
    {
        if (! is_null($this->installed))
            return $this->installed;

        $access = $this->instance->getBestAccess('filetransfer');

        return $this->installed = $access->fileExists(
            $this->instance->getWebPath('tiki-setup.php'));
    } // }}}

    function getInstallType() // {{{
    {
        if (! is_null($this->installType))
            return $this->installType;

        $access = $this->instance->getBestAccess('filetransfer');
        if ($access->fileExists($this->instance->getWebPath('.svn/entries')))
            return $this->installType = 'svn';
        else
            return $this->installType = 'tarball';
    } // }}}

    function install(Version $version) // {{{
    {
        $access = $this->instance->getBestAccess('scripting');
        if ($access instanceof ShellPrompt) {
            $access->shellExec(
                $this->getExtractCommand($version, $this->instance->webroot));
        }
        else {
            $folder = cache_folder($this, $version);
            $this->extractTo($version, $folder);

            $access->copyLocalFolder($folder);
        }

        $this->branch = $version->branch;
        $this->installType = $version->type;
        $this->installed = true;

        $version = $this->registerCurrentInstallation();
        $this->fixPermissions(); // it also runs composer!

        if (! $access->fileExists($this->instance->getWebPath('.htaccess'))) {
            $access->uploadFile(
                $this->instance->getWebPath('_htaccess'),
                $this->instance->getWebPath('.htaccess')
            );
        }

        if ($access instanceof ShellPrompt) {
            $access->shellExec('touch ' .
                escapeshellarg($this->instance->getWebPath('db/lock')));
        }

        $version->collectChecksumFromInstance($this->instance);
    } // }}}

    function getBranch() // {{{
    {
        if ($this->branch)
            return $this->branch;

        if ($this->getInstallType() == 'svn') {
            $svn = new RC_SVN(SVN_TIKIWIKI_URI);
            if ($branch = $svn->getRepositoryBranch($this->instance)) {
                info("Detected SVN : $branch");
                return $this->branch = $branch;
            }
        }

        $access = $this->instance->getBestAccess('filetransfer');
        $content = $access->fileGetContents(
            $this->instance->getWebPath('tiki-setup.php'));

        if (preg_match(
            "/tiki_version\s*=\s*[\"'](\d+\.\d+\.\d+(\.\d+)?)/", $content, $matches)) {
            $version = $matches[1];
            $branch = $this->formatBranch($version);

            echo 'The branch provided may not be correct. ' .
                "Until 1.10 is tagged, use branches/1.10.\n";
            $entry = readline("If this is not correct, enter the one to use: [$branch]");
            if (! empty($entry))
                return $this->branch = $entry;
            else
                return $this->branch = $branch;
        }

        $content = $access->fileGetContents(
            $this->instance->getWebPath('lib/setup/twversion.class.php'));
        if (empty($content)) {
            $content = $access->fileGetContents(
                $this->instance->getWebPath('lib/twversion.class.php'));
        }

        if (preg_match(
            "/this-\>version\s*=\s*[\"'](\d+\.\d+(\.\d+)?(\.\d+)?(\w+)?)/", $content, $matches)) {
            $version = $matches[1];
            $branch = $this->formatBranch($version);

            if (strpos($branch, 'branches/1.10') === 0)
                $branch = 'branches/2.0';

            $entry = readline("If this is not correct, enter the one to use: [$branch]");
            if (! empty($entry))
                return $this->branch = $entry;
        }
        else {
            $branch = '';
            while (empty($branch))
                $branch = readline("No version found. Which tag should be used? (Ex.: (Subversion) branches/1.10) ");
        }

        return $this->branch = $branch;
    } // }}}

    function getUpdateDate() // {{{
    {
        $access = $this->instance->getBestAccess('filetransfer');
        $date = $access->fileModificationDate($this->instance->getWebPath('tiki-setup.php'));

        return $date;
    } // }}}

    function getSourceFile(Version $version, $filename) // {{{
    {
        $dot = strrpos($filename, '.');
        $ext = substr($filename, $dot);

        $local = tempnam(TEMP_FOLDER, 'trim');
        rename($local, $local . $ext);
        $local .= $ext;

        if ($version->type == 'svn') {
            $branch = SVN_TIKIWIKI_URI . "/{$version->branch}/$filename";
            $branch = str_replace('/./', '/', $branch);
            $branch = escapeshellarg($branch);
            `svn export $branch $local`;

            return $local;
        }
    } // }}}

    private function getExtractCommand($version, $folder) // {{{
    {
        if ($version->type == 'svn' || $version->type == 'tarball') {
            $branch = SVN_TIKIWIKI_URI . "/{$version->branch}";
            $branch = str_replace('/./', '/', $branch);
            $branch = escapeshellarg($branch);
            return "svn co $branch $folder";
        }
    } // }}}

    function extractTo(Version $version, $folder) // {{{
    {
        if (file_exists($folder))
            `svn up --non-interactive $folder`;
        else {
            $command = $this->getExtractCommand($version, $folder);
            `$command`;
        }
    } // }}}

    function performActualUpdate(Version $version) // {{{
    {
        switch ($this->getInstallType()) {
        case 'svn':
        case 'tarball':
            $access = $this->instance->getBestAccess('scripting');

            if ($access instanceof ShellPrompt && $access->hasExecutable('svn')) {

                info('Updating svn...');

                $access->shellExec(
                  "rm -Rf " . escapeshellarg( $this->instance->getWebPath('temp/cache'))
                );

                $svn = new RC_SVN(SVN_TIKIWIKI_URI);
                $svn->updateInstanceTo($this->instance, $version->branch);
                $access->shellExec('chmod 0777 temp temp/cache');

                if ($this->instance->isModernTiki()) {
                    info('Updating composer');

                    $ret = $access->shellExec(array(
                      "sh setup.sh composer",
                      "{$this->instance->phpexec} -q -d memory_limit=256M console.php clear:cache --all",
                    ));
                }

            }
            elseif ($access instanceof Mountable) {
                $folder = cache_folder($this, $version);
                $this->extractTo($version, $folder);
                $access->copyLocalFolder($folder);
            }

            info('Updating database schema...');

            $access->runPHP(
                dirname(__FILE__) . '/../../scripts/tiki/sqlupgrade.php',
                array($this->instance->webroot)
            );

            info('Fixing permissions...');

            $this->fixPermissions();
            $access->shellExec('touch ' . escapeshellarg($this->instance->getWebPath('db/lock')));
            return;
        }

        // TODO: Handle fallback
    } // }}}

    function performActualUpgrade(Version $version, $abort_on_conflict) // {{{
    {
        switch ($this->getInstallType()) {
        case 'svn':
        case 'tarball':
            $access = $this->instance->getBestAccess('scripting');

            if ($access instanceof ShellPrompt && $access->hasExecutable('svn')) {

                info('Upgrading svn...');

                $access->shellExec(
                    "rm -Rf " . escapeshellarg( $this->instance->getWebPath('temp/cache'))
                );

                $svn = new RC_SVN(SVN_TIKIWIKI_URI);
                $svn->updateInstanceTo($this->instance, $version->branch);
                $access->shellExec('chmod 0777 temp temp/cache');

                if ($this->instance->isModernTiki()) {
                    info('Updating composer...');

                    $ret = $access->shellExec(array(
                        "sh setup.sh composer",
                        "{$this->instance->phpexec} -q -d memory_limit=256M console.php clear:cache --all",
                    ));
                }

                info('Updating database schema...');

                $access->runPHP(
                  dirname(__FILE__) . '/../../scripts/tiki/sqlupgrade.php',
                  array($this->instance->webroot)
                );

                info('Fixing permissions...');

                $this->fixPermissions();
                $access->shellExec('touch ' . escapeshellarg($this->instance->getWebPath('db/lock')));
                return;
            }
        }
    } // }}}

    private function formatBranch($version) // {{{
    {
        if (substr($version, 0, 4) == '1.9.')
            return 'REL-' . str_replace('.', '-', $version);
        elseif ($this->getInstallType() == 'svn')
            return "tags/$version";
        elseif ($this->getInstallType() == 'tarball')
            return "tags/$version";
    } // }}}

    function fixPermissions() // {{{
    {
        $access = $this->instance->getBestAccess('scripting');

        if ($access instanceof ShellPrompt) {
            $webroot = $this->instance->webroot;
            $access->chdir($this->instance->webroot);

            if ($this->instance->isModernTiki()) {
                $ret = $access->shellExec("cd $webroot && bash setup.sh -n fix");    // does composer as well
            } else {
                warning('Old Tiki detected, running bundled TRIM setup.sh script.');
                $filename = $this->instance->getWorkPath('setup.sh');
                $access->uploadFile(dirname(__FILE__) . '/../../scripts/setup.sh', $filename);
                $ret = $access->shellExec("cd $webroot && bash " . escapeshellarg($filename));
            }
        }
    } // }}}

    function getFileLocations() // {{{
    {
        $access = $this->instance->getBestAccess('scripting');
        $out = $access->runPHP(
            dirname(__FILE__) . '/../../scripts/tiki/get_directory_list.php',
            array($this->instance->webroot)
        );

        $folders['app'] = array($this->instance->webroot);

        foreach (explode("\n", $out ) as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $line = rtrim($line, '/');

            if ($line{0} != '/')
                $line = "{$this->instance->webroot}/$line";

            if (! empty($line))
                $folders['data'][] = $line;
        }

        return $folders;
    } // }}}

    function requiresDatabase() // {{{
    {
        return true;
    } // }}}

    function getAcceptableExtensions() // {{{
    {
        return array('mysqli', 'mysql');
    } // }}}

    function setupDatabase(Database $database) // {{{
    {
        $tmp = tempnam(TEMP_FOLDER, 'dblocal');
        file_put_contents($tmp, <<<LOCAL
<?php
\$db_tiki='{$database->type}';
\$host_tiki='{$database->host}';
\$user_tiki='{$database->user}';
\$pass_tiki='{$database->pass}';
\$dbs_tiki='{$database->dbname}';
\$client_charset = 'utf8';

LOCAL
);
        $access = $this->instance->getBestAccess('filetransfer');
        $access->uploadFile($tmp, 'db/local.php');
        $access->shellExec("chmod 0664 {$this->instance->webroot}/db/local.php");
        // TODO: Hard-coding: 'apache:apache'
        // TODO: File ownership under the webroot should be configurable per instance.
        $access->shellExec("chown apache:apache {$this->instance->webroot}/db/local.php");

        if ($access->fileExists('console.php') && $access instanceof ShellPrompt) {

            info( "Updating svn, composer, perms & database..." );

            $access = $this->instance->getBestAccess('scripting');
            $access->chdir($this->instance->webroot);
            $ret = $access->shellExec(array(
                "{$this->instance->phpexec} -q -d memory_limit=256M console.php database:install",
                'touch ' . escapeshellarg($this->instance->getWebPath('db/lock')),
            ));

        }
        else if ($access->fileExists('installer/shell.php')) {
            if ($access instanceof ShellPrompt) {
                $access = $this->instance->getBestAccess('scripting');
                $access->chdir($this->instance->webroot);
                $access->shellExec($this->instance->phpexec . ' installer/shell.php install');
            }
            else {
                $access->runPHP(
                    dirname(__FILE__) . '/../../scripts/tiki/tiki_dbinstall_ftp.php',
                    array($this->instance->webroot)
                );
            }
        }
        else {
            // FIXME: Not FTP compatible ? prior to 3.0 only
            $access = $this->instance->getBestAccess('scripting');
            $file = $this->instance->getWebPath('db/tiki.sql');
            $root = $this->instance->webroot;
            $access->runPHP(
                dirname(__FILE__) . '/../../scripts/tiki/run_sql_file.php',
                array($root, $file));
            $access->runPHP(
                dirname(__FILE__) . '/../../scripts/tiki/sqlupgrade.php',
                array($this->instance->webroot));
        }

        echo "Verify if you have db/local.php file, if you don't put the following content in it.\n";
        echo "<?php
\$db_tiki='{$database->type}';
\$host_tiki='{$database->host}';
\$user_tiki='{$database->user}';
\$pass_tiki='{$database->pass}';
\$dbs_tiki='{$database->dbname}';
\$client_charset = 'utf8';
";
    } // }}}

    function restoreDatabase(Database $database, $remoteFile) // {{{
    {
        $tmp = tempnam(TEMP_FOLDER, 'dblocal');
        file_put_contents($tmp, <<<LOCAL
<?php
\$db_tiki='{$database->type}';
\$dbversion_tiki='2.0';
\$host_tiki='{$database->host}';
\$user_tiki='{$database->user}';
\$pass_tiki='{$database->pass}';
\$dbs_tiki='{$database->dbname}';

LOCAL
);
        $access = $this->instance->getBestAccess('filetransfer');
        $access->uploadFile($tmp, 'db/local.php');

        $access = $this->instance->getBestAccess('scripting');
        $root = $this->instance->webroot;

        // FIXME: Not FTP compatible (arguments)
        info("Loading '$remoteFile' into '{$database->dbname}'");
        $access->runPHP(
            dirname(__FILE__) . '/../../scripts/tiki/run_sql_file.php',
            array($root, $remoteFile));
    } // }}}

    function backupDatabase($target) // {{{
    {
        $access = $this->instance->getBestAccess('scripting');
        if ($access instanceof ShellPrompt) {
            $randomName = md5(time() . 'trimbackup') . '.sql.gz';
            $remoteFile = $this->instance->getWorkPath($randomName);
            $access->runPHP(
                dirname(__FILE__) . '/../../scripts/tiki/backup_database.php',
                array($this->instance->webroot, $remoteFile));
            $localName = $access->downloadFile($remoteFile);
            $access->deleteFile($remoteFile);

            `zcat $localName > '$target'`;
            unlink($localName);
        }
        else {
            $data = $access->runPHP(
                dirname(__FILE__) . '/../../tiki/scripts/mysqldump.php');
            file_put_contents($target, $data);
        }
    } // }}}

    function beforeChecksumCollect() // {{{
    {
        $this->removeTemporaryFiles();
    } // }}}

    function installProfile($domain, $profile) {
        $access = $this->instance->getBestAccess('scripting');

        echo $access->runPHP(
            dirname(__FILE__) . '/../../scripts/tiki/remote_install_profile.php',
            array($this->instance->webroot, $domain, $profile));
    }

    function removeTemporaryFiles()
    {
        $access = $this->instance->getBestAccess('scripting');
        // FIXME: Not FTP compatible
        if ($access instanceof ShellPrompt) {
        	// templates_c/ moved to temp/ in Tiki 17.
	        if (is_dir($this->instance->getWebPath('templates_c/'))) {
		        $path = $this->instance->getWebPath('templates_c/*[!index].php');
	        } else {
		        $path = $this->instance->getWebPath('temp/templates_c/*[!index].php');
	        }
	        
        	if (strlen($path) < 5) {
        		// Be especially careful not to remove '/' in case getWebPath() returns an unexpected value.
        		throw new UnexpectedValueException();
        	}
            // --force ignores prompting if files are write-protected and will silence the warning in case no file matches.
            $access->shellExec("rm --force $path");
        }
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
