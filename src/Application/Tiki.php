<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application;

use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Access\FTP;
use TikiManager\Access\Mountable;
use TikiManager\Access\ShellPrompt;
use TikiManager\Application\Exception\VcsException;
use TikiManager\Application\Tiki\Versions\Fetcher\YamlFetcher;
use TikiManager\Application\Tiki\Versions\TikiRequirementsHelper;
use TikiManager\Config\App;
use TikiManager\Config\Environment;
use TikiManager\Libs\Database\Database;
use TikiManager\Libs\Helpers\ApplicationHelper;
use TikiManager\Libs\Host\Exception\CommandException;
use TikiManager\Libs\VersionControl\Git;
use TikiManager\Libs\VersionControl\Src;
use TikiManager\Libs\VersionControl\Svn;

class Tiki extends Application
{
    private $installType = null;
    private $branch = null;
    private $installed = null;
    /** @var Svn|Git|Src  */
    private $vcs_instance = null;

    public static $excludeBackupFolders = [
        'vendor',
        'vendor_bundled/vendor',
        'temp',
        'bin',
        'modules/cache'
    ];

    public function __construct(Instance $instance)
    {
        parent::__construct($instance);

        if (!$instance->vcs_type) {
            $instance->vcs_type = $this->getInstallType(true);
        }

        $this->vcs_instance = $instance->getVersionControlSystem();
    }

    /**
     * @param string $targetFile
     * @return bool
     */
    public function backupDatabase(string $targetFile): bool
    {
        $access = $this->instance->getBestAccess('scripting');

        if (!$access instanceof ShellPrompt || (ApplicationHelper::isWindows() && $this->instance->type == 'local')) {
            $data = $access->runPHP(
                dirname(__FILE__) . '/../../scripts/tiki/mysqldump.php'
            );

            return (bool)file_put_contents($targetFile, $data);
        }

        $randomName = md5(time() . 'backup') . '.sql';
        $remoteFile = $this->instance->getWorkPath($randomName);

        $access->runPHP(
            dirname(__FILE__) . '/../../scripts/tiki/backup_database.php',
            [$this->instance->webroot, $remoteFile]
        );

        $access->downloadFile($remoteFile, $targetFile);
        $access->deleteFile($remoteFile);

        return file_exists($targetFile);
    }

    public function beforeChecksumCollect()
    {
        $this->removeTemporaryFiles();
    }

    /**
     * @param Version $version
     * @param $folder
     */
    public function extractTo(Version $version, $folder): void
    {
        $dirExists = file_exists($folder);

        if ($dirExists && preg_match('/tags\\//', $version->branch)) {
            // Tags are unchangeable
            return;
        }

        $this->vcs_instance->setRunLocally(true);

        if ($dirExists) {
            try {
                $this->io->writeln('Updating cache repository from server... <fg=yellow>[may take a while]</>');
                $this->vcs_instance->revert($folder);
                $this->vcs_instance->pull($folder);
            } catch (VcsException $e) {
                $this->io->writeln('<error>Failed to update existing cache repository.</error>');
                trim_output($e->getMessage());

                // Delete the existing cache and attempt to clone
                $fs = new Filesystem();
                $fs->remove($folder);
                $this->extractTo($version, $folder);
            }
        } else {
            $this->io->writeln('Cloning cache repository from server... <fg=yellow>[may take a while]</>');
            $this->vcs_instance->clone($version->branch, $folder);
        }

        $this->vcs_instance->setRunLocally(false);
    }

    /**
     * Get repository revision information
     *
     * @param string|null $folder If valid folder or null it will collect the svn revision from the folder|instance webroot.
     * @return int
     */
    public function getRevision($folder = null)
    {
        $revision = '';
        $access = $this->instance->getBestAccess('scripting');
        $can_svn = $access->hasExecutable('svn') && $this->vcs_instance->getIdentifier() == 'SVN';
        $can_git = $access->hasExecutable('git') && $this->vcs_instance->getIdentifier() == 'GIT';

        if ($access instanceof ShellPrompt && ($can_git || $can_svn)) {
            $revision = $this->vcs_instance->getRevision($folder);
        }

        return $revision;
    }

    /**
     * Fixing files read/write permissions and run composer install
     * @throws \Exception
     */
    public function fixPermissions()
    {
        $instance = $this->instance;
        $access = $instance->getBestAccess('scripting');

        if ($instance->type === 'local' && !getenv('COMPOSER_HOME') && PHP_SAPI !== 'cli') {
            $access->setenv('COMPOSER_HOME', $_ENV['CACHE_FOLDER'] . DIRECTORY_SEPARATOR . '.composer');
        }

        if ($access instanceof ShellPrompt) {
            $access->chdir($instance->webroot);

            if ($instance->hasConsole()) {
                if ($instance->type == 'local' && ApplicationHelper::isWindows()) {
                    // TODO Requires implementation
                    // There is no console command nor php script to fix permissions
                    return;
                } else {
                    $extraParameters = ['-n']; //by default detect the current user and do not ask

                    // detect (guess) if we should pass a given user to the command, normally when running as root
                    if ($access->user == 'root' && preg_match('/\/home\/([^\/]+)\/.*/', $instance->webroot, $matches)) {
                        // get the group for the user
                        $groupCommand = $access->createCommand('id', ['-g', '-n', $matches[1]]);
                        $groupCommand->run();
                        $group = trim($groupCommand->getStdoutContent());

                        $extraParameters = ['-u', $matches[1], '-g', $group ?: $matches[1]];
                    }

                    $command = $access->createCommand('bash', array_merge(['setup.sh'], $extraParameters, ['fix'])); // does composer as well
                }
            } else {
                $this->io->warning('Old Tiki detected, running bundled Tiki Manager setup.sh script.');
                $filename = $instance->getWorkPath('setup.sh');
                $access->uploadFile(dirname(__FILE__) . '/../../scripts/setup.sh', $filename);
                $command = $access->createCommand('bash', ['$filename']); // does composer as well
            }

            $command->run();
            if ($command->getReturn() !== 0) {
                // Because temp/composer.phar does not exist, the script will return 1.
                // We do not throw error if instance type is src (composer install is not required)
                $output = $command->getStdoutContent();

                if ((!$this->vcs_instance instanceof Src) ||
                    (strpos($output, 'We have failed to obtain the composer executable') === false)) {
                    throw new \Exception('Command failed');
                }
            }
        }
    }

    private function formatBranch($version)
    {
        if (substr($version, 0, 4) == '1.9.') {
            return 'REL-' . str_replace('.', '-', $version);
        } elseif ($this->getInstallType() == 'svn') {
            return "tags/$version";
        } elseif ($this->getInstallType() == 'tarball') {
            return "tags/$version";
        }
    }

    public function getBaseVersion(): string
    {
        $baseVersion = 'master';
        $branch = $this->getBranch(true);
        if (preg_match('/(\d+|trunk|master)/', $branch, $matches)) {
            $baseVersion = $matches[1];
        }

        return $baseVersion;
    }

    public function getAcceptableExtensions()
    {
        return ['mysqli', 'mysql'];
    }

    public function getBranch($refresh = false)
    {
        if ($this->branch && !$refresh) {
            return $this->branch;
        }

        if (! is_null($this->vcs_instance)) {
            $branch = $this->vcs_instance->getRepositoryBranch($this->instance->webroot);

            if ($branch) {
                return $this->branch = $branch;
            }
        }

        $access = $this->instance->getBestAccess('filetransfer');
        $content = $access->fileGetContents(
            $this->instance->getWebPath('tiki-setup.php')
        );

        if (preg_match(
            "/tiki_version\s*=\s*[\"'](\d+\.\d+\.\d+(\.\d+)?)/",
            $content,
            $matches
        )) {
            $version = $matches[1];
            $branch = $this->formatBranch($version);

            $this->io->writeln('The branch provided may not be correct. Until 1.10 is tagged, use branches/1.10.');
            $entry = $this->io->ask('If this is not correct, enter the one to use:', $branch);

            if (! empty($entry)) {
                return $this->branch = $entry;
            } else {
                return $this->branch = $branch;
            }
        }

        $content = $access->fileGetContents(
            $this->instance->getWebPath('lib/setup/twversion.class.php')
        );
        if (empty($content)) {
            $content = $access->fileGetContents(
                $this->instance->getWebPath('lib/twversion.class.php')
            );
        }

        if (preg_match(
            "/this-\>version\s*=\s*[\"'](\d+\.\d+(\.\d+)?(\.\d+)?(\w+)?)/",
            $content,
            $matches
        )) {
            $version = $matches[1];
            $branch = $this->formatBranch($version);

            if (strpos($branch, 'branches/1.10') === 0) {
                $branch = 'branches/2.0';
            }

            $entry = $this->io->ask('If this is not correct, enter the one to use:', $branch);

            if (! empty($entry)) {
                return $this->branch = $entry;
            }
        } else {
            $branch = '';
            while (empty($branch)) {
                $branch = $this->io->ask('No version found. Which tag should be used? (Ex.: (Subversion) branches/1.10) ');
            }
        }

        return $this->branch = $branch;
    }

    public function getFileLocations()
    {
        $access = $this->instance->getBestAccess('scripting');
        $webroot = rtrim($this->instance->webroot, '/');
        $out = $access->runPHP(
            dirname(__FILE__) . '/../../scripts/tiki/get_directory_list.php',
            [$webroot]
        );

        $folders['app'] = [$webroot];

        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            if (empty($line) || $this->checkFileLocationOutput($line)) {
                continue;
            }

            $line = rtrim($line, '/');

            if (! empty($line)) {
                $folders['data'][] = $line;
            }
        }

        return $folders;
    }

    /**
     * Check output line for unwanted messages
     * so they are not treated as locations
     *
     * @param $line
     * @return bool
     */
    public function checkFileLocationOutput($line)
    {
        $result = false;

        if (strpos($line, '[Warning]') !== false) {
            $result = true;
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getSystemIniConfigFilePath()
    {
        $access = $this->instance->getBestAccess('scripting');
        $out = $access->runPHP(
            $_ENV['TRIM_ROOT'] . '/scripts/tiki/get_system_config_ini_file.php',
            [$this->instance->webroot]
        );

        return $out;
    }

    public function getInstallType($refresh = false)
    {
        if (! is_null($this->installType) && !$refresh) {
            return $this->installType;
        }

        $access = $this->instance->getBestAccess('filetransfer');

        $checkpaths = [
            $this->instance->getWebPath('.svn/entries') => 'svn',
            $this->instance->getWebPath('.svn/wc.db') => 'svn',
            $this->instance->getWebPath('.git/HEAD') => 'git',
            $this->instance->getWebPath('tiki-setup.php') => 'src',
        ];

        $installType = null;
        foreach ($checkpaths as $path => $type) {
            if ($access->fileExists($path)) {
                $installType = $type;
                break;
            }
        }

        return $this->installType = $installType;
    }

    public function getName()
    {
        return 'tiki';
    }

    public function getSourceFile(Version $version, $filename)
    {
        $dot = strrpos($filename, '.');
        $ext = substr($filename, $dot);

        $local = tempnam($_ENV['TEMP_FOLDER'], 'trim');
        rename($local, $local . $ext);
        $local .= $ext;

        $sourcefile = $_ENV['SVN_TIKIWIKI_URI'] . "/{$version->branch}/$filename";
        $sourcefile = str_replace('/./', '/', $sourcefile);

        $content = file_get_contents($sourcefile);
        file_put_contents($local, $content);

        return $local;
    }

    public function getUpdateDate()
    {
        $access = $this->instance->getBestAccess('filetransfer');
        $date = $access->fileModificationDate($this->instance->getWebPath('tiki-setup.php'));

        return $date;
    }

    public function getVersions()
    {
        return $this->vcs_instance->getAvailableBranches();
    }

    /**
     * Install new instance.
     *
     * @param Version $version
     * @param bool $checksumCheck
     * @return null
     */
    public function install(Version $version, $checksumCheck = false)
    {
        $access = $this->instance->getBestAccess('scripting');
        $host = $access->getHost();

        $folder = cache_folder($this, $version);
        $this->extractTo($version, $folder);

        $this->io->writeln('<info>Installing Tiki ' . $version->branch . ' using ' . $version->type . '</info>');

        if ($access instanceof ShellPrompt) {
            $this->io->writeln('Copying files to webroot folder...');
            if (ApplicationHelper::isWindows() && $this->instance->type == 'local') {
                $host->windowsSync(
                    $folder,
                    $this->instance->webroot,
                    null,
                    ['.svn/tmp']
                );
            } else {
                $host->rsync([
                    'src' => rtrim($folder, '/') . '/',
                    'dest' => rtrim($this->instance->webroot, '/') . '/',
                    'exclude' => ['.phpenv']
                ]);
            }
        } else {
            $access->copyLocalFolder($folder);
        }

        $this->branch = $version->branch;
        $this->installType = $version->type;
        $this->installed = true;

        $version = $this->registerCurrentInstallation();
        $this->installComposer();
        $this->installComposerDependencies(); // fix permissions does not return a proper exit code if composer fails
        $this->installTikiPackages();
        $this->fixPermissions();

        $this->instance->configureHtaccess();

        $this->setDbLock();

        if ($checksumCheck) {
            $this->io->writeln('Collecting files checksum from instance...');
            $version->collectChecksumFromInstance($this->instance);
        }
    }

    public function installProfile($domain, $profile)
    {
        $access = $this->instance->getBestAccess('scripting');

        $output = $access->runPHP(
            dirname(__FILE__) . '/../../scripts/tiki/remote_install_profile.php',
            [$this->instance->webroot, $domain, $profile]
        );

        $this->io->writeln($output);
    }

    public function isInstalled()
    {
        if (! is_null($this->installed)) {
            return $this->installed;
        }

        $access = $this->instance->getBestAccess('filetransfer');
        $checkpath = $this->instance->getWebPath('tiki-setup.php');
        $this->installed = $access->fileExists($checkpath);
        return $this->installed;
    }

    public function performActualUpdate(Version $version, $options = [])
    {
        $access = $this->instance->getBestAccess('scripting');
        $vcsType = $this->vcs_instance->getIdentifier();
        $can_svn = $access->hasExecutable('svn') && $vcsType == 'SVN';
        $can_git = $access->hasExecutable('git') && $vcsType == 'GIT';

        if ($access instanceof ShellPrompt && ($can_git || $can_svn || $vcsType === 'SRC')) {
            $escaped_root_path = escapeshellarg(rtrim($this->instance->webroot, '/\\'));
            $escaped_temp_path = escapeshellarg(rtrim($this->instance->getWebPath('temp'), '/\\'));
            $escaped_cache_path = escapeshellarg(rtrim($this->instance->getWebPath('temp/cache'), '/\\'));

            if ($vcsType === 'SRC') {
                $version->branch = $this->vcs_instance->getBranchToUpdate($version->branch);
            }

            $lag = $options['lag'] ?? 0;
            $this->vcs_instance->update($this->instance->webroot, $version->branch, $lag);
            foreach ([$escaped_temp_path, $escaped_cache_path] as $path) {
                $script = sprintf('chmod(%s, 0777);', $path);
                $access->createCommand($this->instance->phpexec, ["-r {$script}"])->run();
            }
        } elseif ($access instanceof Mountable) {
            $folder = cache_folder($this, $version);
            $this->extractTo($version, $folder);
            $access->copyLocalFolder($folder);
        }

        $this->postInstall($options);
    }

    public function performActualUpgrade(Version $version, $options = [])
    {
        $access = $this->instance->getBestAccess('scripting');
        $can_svn = $access->hasExecutable('svn') && $this->vcs_instance->getIdentifier() == 'SVN';
        $can_git = $access->hasExecutable('git') && $this->vcs_instance->getIdentifier() == 'GIT';

        $access->getHost(); // trigger the config of the location change (to catch phpenv)

        if (!$access instanceof ShellPrompt ||  !($can_svn || $can_git || $this->vcs_instance->getIdentifier() == 'SRC')) {
            return;
        }

        $lag = $options['lag'] ?? 0;

        $this->clearCache();
        $this->moveVendor();
        $this->vcs_instance->update($this->instance->webroot, $version->branch, $lag);
        foreach (['temp', 'temp/cache'] as $path) {
            $script = sprintf('chmod(%s, 0777);', $path);
            $access->createCommand($this->instance->phpexec, ["-r {$script}"])->run();
        }

        $this->postInstall($options);
    }

    public function removeTemporaryFiles()
    {
        $access = $this->instance->getBestAccess('scripting');

        // FIXME: Not FTP compatible
        if ($access instanceof ShellPrompt) {
            $this->clearCache(true);
            $this->vcs_instance->cleanup($this->instance->webroot);
        }
    }

    public function requiresDatabase()
    {
        return true;
    }

    public function deleteAllTables()
    {
        $access = $this->instance->getBestAccess('scripting');
        $root = $this->instance->webroot;
        $access->runPHP(
            dirname(__FILE__) . '/../../scripts/tiki/run_delete_tables.php',
            [$root]
        );
    }

    public function restoreDatabase(Database $database, $remoteFile)
    {
        $tmp = tempnam($_ENV['TEMP_FOLDER'], 'dblocal');

        if (! empty($database->dbLocalContent)) {
            file_put_contents($tmp, $database->dbLocalContent);
        } else {
            $systemConfigFilePath = '';

            if (isset($this->instance->system_config_file)) {
                $srcSystemConfigFilePath = $this->instance->system_config_file;
                if (! empty($srcSystemConfigFilePath)) {
                    $systemConfigFilePath = "\n\$system_configuration_file='{$srcSystemConfigFilePath}';\n";
                }
            }

            $fileContent = $this->generateDbFileContent($database);
            $fileContent .= $systemConfigFilePath;

            file_put_contents($tmp, $fileContent);
        }

        $access = $this->instance->getBestAccess('filetransfer');
        $access->uploadFile($tmp, 'db/local.php');

        $access = $this->instance->getBestAccess('scripting');
        $root = $this->instance->webroot;

        $this->io->writeln("Removing existing tables from '{$database->dbname}'");
        $this->deleteAllTables();

        // FIXME: Not FTP compatible (arguments)
        $this->io->writeln("Loading '$remoteFile' into '{$database->dbname}'");
        $output = $access->runPHP(
            dirname(__FILE__) . '/../../scripts/tiki/run_sql_file.php',
            [$root, $remoteFile]
        );

        $output = !empty($output) ? explode(PHP_EOL, $output) : [];

        $errors = [];
        foreach ($output as $message) {
            if (strpos(strtolower($message), 'error') !== false) {
                array_push($errors, $message);
            }
        }

        if (!empty($errors)) {
            throw new \RuntimeException(implode(PHP_EOL, $errors));
        }
    }

//----------------------------------------------------------------
    public function setupDatabase(Database $database)
    {
        $tmp = tempnam($_ENV['TEMP_FOLDER'], 'dblocal');
        $dbFileContents = $this->generateDbFileContent($database);
        file_put_contents($tmp, $dbFileContents);

        $access = $this->instance->getBestAccess('filetransfer');
        $access->uploadFile($tmp, 'db/local.php');

        $this->io->writeln('Setting db config file...');
        if ($access instanceof ShellPrompt) {
            $script = sprintf("chmod('%s', 0664);", "{$this->instance->webroot}/db/local.php");
            $access->createCommand($this->instance->phpexec, ["-r {$script}"])->run();

            // TODO: Hard-coding: 'apache:apache'
            // TODO: File ownership under the webroot should be configurable per instance.
            $script = sprintf("chown('%s', 'apache');", "{$this->instance->webroot}/db/local.php");
            $access->createCommand($this->instance->phpexec, ["-r {$script}"])->run();

            $script = sprintf("chgrp('%s', 'apache');", "{$this->instance->webroot}/db/local.php");
            $access->createCommand($this->instance->phpexec, ["-r {$script}"])->run();
        }

        $this->io->writeln('Installing database... <fg=yellow>[may take a while]</>');
        if ($access->fileExists('console.php') && $access instanceof ShellPrompt) {
            $access = $this->instance->getBestAccess('scripting');
            $access->chdir($this->instance->webroot);
            $access->shellExec("{$this->instance->phpexec} -q -d memory_limit=256M console.php database:install");
            $this->setDbLock();
        } elseif ($access->fileExists('installer/shell.php')) {
            if ($access instanceof ShellPrompt) {
                $access = $this->instance->getBestAccess('scripting');
                $access->chdir($this->instance->webroot);
                $access->shellExec($this->instance->phpexec . ' installer/shell.php install');
            } else {
                $access->runPHP(
                    dirname(__FILE__) . '/../../scripts/tiki/tiki_dbinstall_ftp.php',
                    [$this->instance->webroot]
                );
            }
        } else {
            // FIXME: Not FTP compatible ? prior to 3.0 only
            $access = $this->instance->getBestAccess('scripting');
            $file = $this->instance->getWebPath('db/tiki.sql');
            $root = $this->instance->webroot;
            $access->runPHP(
                dirname(__FILE__) . '/../../scripts/tiki/run_sql_file.php',
                [$root, $file]
            );
            $access->runPHP(
                dirname(__FILE__) . '/../../scripts/tiki/sqlupgrade.php',
                [$this->instance->webroot]
            );
        }

        $message = <<<TXT
Verify if you have db/local.php file, if you don't put the following content in it.
{$dbFileContents}
TXT;

        $this->io->writeln($message);
    }

    /**
     * Set db/lock in instance
     *
     * @return bool
     */
    public function setDbLock()
    {
        $access = $this->instance->getBestAccess('scripting');
        if ($access instanceof ShellPrompt) {
            $script = "echo touch('db/lock');";
            $command = $access->createCommand($access->getInterpreterPath(), ["-r {$script}"])->run();

            return $command->getStdoutContent() == 1;
        }

        return false;
    }

    /**
     * Return a list of the compatible versions of Tiki in instance
     *
     * @param bool $withBlank
     * @return array
     */
    public function getCompatibleVersions($withBlank = true)
    {
        $versions = $this->getVersions();
        $checkTikiVersionRequirement = $this->getTikiRequirementsHelper();
        $compatible = [];
        foreach ($versions as $key => $version) {
            preg_match('/(\d+\.|trunk|master)/', $version->branch, $matches);
            if (!array_key_exists(0, $matches)) {
                continue;
            }

            $req = $checkTikiVersionRequirement->findByBranchName($version->branch);
            if ($req && $req->checkRequirements($this->instance)) {
                $compatible[$key] = $version;
            }
        }

        if ($withBlank) {
            $compatible['-1'] = 'blank : none';
        }

        return $compatible;
    }

    public function installComposer()
    {
        if ($this->instance->getBestAccess()->fileExists('temp/composer.phar')) {
            return;
        }

        $fs = new Filesystem();
        $cacheFile = Environment::get('CACHE_FOLDER') . '/composer_v1.phar';

        if (!$fs->exists($cacheFile)) {
            $tmp = Environment::get('TEMP_FOLDER');
            $path = installComposer($tmp, 1);
            $fs->rename($path, $cacheFile);
        }

        $this->instance->getBestAccess()->uploadFile($cacheFile, 'temp/composer.phar');
    }

    /**
     * Run composer install
     *
     * @return null
     * @throws \Exception
     */
    public function installComposerDependencies()
    {
        $this->io->writeln('Installing composer dependencies... <fg=yellow>[may take a while]</>');

        $instance = $this->instance;
        $access = $instance->getBestAccess('scripting');

        if ($instance->type === 'local' && !getenv('COMPOSER_HOME') && PHP_SAPI !== 'cli') {
            $access->setenv('COMPOSER_HOME', $_ENV['CACHE_FOLDER'] . DIRECTORY_SEPARATOR . '.composer');
        }

        $access->setenv('COMPOSER_DISCARD_CHANGES', 'true');
        $access->setenv('COMPOSER_NO_INTERACTION', '1');

        if (! $access instanceof ShellPrompt || ! $instance->hasConsole()) {
            return;
        }

        $access->chdir($instance->webroot);

        $command = $access->createCommand('bash', ['setup.sh', 'composer']);
        if ($instance->type == 'local' && ApplicationHelper::isWindows()) {
            $command = $access->createCommand('composer', ['install', '-d vendor_bundled', '--no-interaction', '--prefer-dist', '--no-dev']);
        }

        $command->run();
        $commandOutput = $command->getStderrContent() ?: $command->getStdoutContent();

        $bundled =  $this->supportsVendorBundled() ? 'vendor_bundled/' : '';

        if ($command->getReturn() !== 0 ||
            !$access->fileExists($bundled . 'vendor/autoload.php') ||
            preg_match('/Your requirements could not be resolved/', $commandOutput)
        ) {
            trim_output($commandOutput);
            throw new \Exception("Composer install failed for {$bundled}composer.lock (Tiki bundled packages).\nCheck " . $_ENV['TRIM_OUTPUT'] . " for more details.");
        }
    }

    public function installTikiPackages(bool $update = false)
    {
        $instance = $this->instance;
        $access = $instance->getBestAccess();

        if (!$this->supportsTikiPackages() || !$access->fileExists('composer.json')) {
            return;
        }

        $msg = ($update ? 'Updating' : 'Installing') . ' Tiki Packages... <fg=yellow>[may take a while]</>';
        $this->io->writeln($msg);

        if ($update && $this->updateTikiPackages()) {
            return;
        }

        $action = $update ? 'update' : 'install';

        $command = $access->createCommand(
            $this->instance->phpexec,
            ['temp/composer.phar', $action, '--no-interaction', '--prefer-dist', '--no-ansi', '--no-progress']
        );

        $command->run();

        if ($command->getReturn() !== 0 || !$access->fileExists('vendor/autoload.php')) {
            $commandOutput = $command->getStderrContent() ?: $command->getStdoutContent();
            trim_output($commandOutput);

            $errorMsg = "Failed to " . $action. " Tiki Packages listed in composer.json in the root folder.";

            $this->io->error($errorMsg . "\nCheck " . $_ENV['TRIM_OUTPUT'] . " for more details.");
        }
    }

    /**
     * @return bool True if operation completed, false if not supported
     */
    protected function updateTikiPackages(): bool
    {
        $access = $this->instance->getBestAccess();
        $command = $access->createCommand(
            $this->instance->phpexec,
            ['console.php', 'package:update', '--all', '--handle-deprecated']
        );

        $errorMsg = "Failed to update Tiki Packages.\nCheck " . $_ENV['TRIM_OUTPUT'] . " for more details.";
        $command->run();

        $commandOutput = $command->getStderrContent() ?: $command->getStdoutContent();

        // If the command do not support the --handle-deprecated option
        if ($command->getReturn() !== 0 && preg_match('/option does not exist/', $commandOutput)) {
            return false;
        }

        if ($command->getReturn() !== 0) {
            trim_output($commandOutput);
            $this->io->error($errorMsg);
        }

        return true;
    }

    /**
     * @param array $options
     * @throws \Exception
     */
    public function postInstall(array $options = [])
    {
        $access = $this->instance->getBestAccess('scripting');
        $access->getHost(); // trigger the config of the location change (to catch phpenv)

        if ($this->vcs_instance->getIdentifier() != 'SRC') {
            $this->installComposer();
            $this->installComposerDependencies();
        }

        $this->io->writeln('Updating database schema...');
        $this->runDatabaseUpdate();

        $this->setDbLock();

        $this->installTikiPackages(true);

        $hasConsole = $this->instance->hasConsole();

        if ($hasConsole) {
            $this->io->writeln('Cleaning cache...');
            $this->clearCache();

            if (empty($options['skip-cache-warmup'])) {
                $this->io->writeln('Generating caches... <fg=yellow>[may take a while]</>');
                $access->shellExec("{$this->instance->phpexec} -q -d memory_limit=256M console.php cache:generate");
            }

            if (empty($options['skip-reindex'])) {
                if (!empty($options['live-reindex'])) {
                    $this->io->writeln('Fixing permissions...');
                    $this->fixPermissions();

                    $this->instance->unlock();
                }

                $this->io->writeln('Rebuilding Index... <fg=yellow>[may take a while]</>');
                if (! $this->instance->reindex()) {
                    $this->io->error('Rebuilding Index failed.');
                }
            }
        }

        $this->io->writeln('Fixing permissions...');
        $this->fixPermissions();
    }

    /**
     * This function renames de vendor folder, when upgrading to 17.x or newer.
     *
     * @return void
     */
    protected function moveVendor()
    {
        $access = $this->instance->getBestAccess();
        if ($access->fileExists('vendor') && !$this->vcs_instance->isFileVersioned($this->instance->webroot, 'vendor')) {
            $access->moveFile(
                $this->instance->webroot . DIRECTORY_SEPARATOR . 'vendor',
                $this->instance->webroot . DIRECTORY_SEPARATOR . 'vendor_old'
            );
            $this->io->warning('Vendor folder was renamed to vendor_old because can cause conflicts with the new version.');
        }
    }

    protected function supportsVendorBundled(): bool
    {
        // vendor_bundled was introduced in Tiki 17
        $baseVersion = $this->instance->getApplication()->getBaseVersion();

        return $baseVersion >= 17 || $baseVersion == 'master' || $baseVersion == 'trunk';
    }

    protected function supportsTikiPackages(): bool
    {
        // Tiki Packages were introduces in 18.x
        $baseVersion = $this->instance->getApplication()->getBaseVersion();

        return $baseVersion >= 18 || $baseVersion == 'master' || $baseVersion == 'trunk';
    }

    public function clearCache($all = false)
    {
        if ($this->instance->hasConsole()) {
            $access = $this->instance->getBestAccess('scripting');
            $flag = $all ? ' --all' : '';
            $access->shellExec("{$this->instance->phpexec} -q -d memory_limit=256M console.php cache:clear" . $flag);

            return true;
        }

        return false;
    }

    public function runDatabaseUpdate()
    {
        $access = $this->instance->getBestAccess('scripting');
        if (!$access instanceof FTP && $this->instance->hasConsole()) {
            $command = $access->createCommand(
                $this->instance->phpexec,
                ['-q', '-d', 'memory_limit=256M', 'console.php', 'database:update']
            );
            $command->run();
            if ($command->getReturn() !== 0) {
                $message = 'Failed to update database. For more information check the logs or access instance and run `php console.php database:update`.';
                App::get('io')->writeln('<error>' . $message . '</error>');
                debug($command->getStdoutContent(), $this->instance->name);
            }
        } else {
            $access->runPHP(
                dirname(__FILE__) . '/../../scripts/tiki/sqlupgrade.php',
                [$this->instance->webroot]
            );
        }
    }

    protected function generateDbFileContent(Database $database)
    {
        $date = date('Y-m-d H:i:s +Z');
        $charset = 'utf8mb4';

        if (!empty($this->branch) && preg_match('/\d+/', $this->branch, $matches)) {
            $charset = $matches[0] < '19' ? 'utf8' : 'utf8mb4';
        }

        $content = <<<TXT
<?php
\$db_tiki='{$database->type}';
\$host_tiki='{$database->host}';
\$user_tiki='{$database->user}';
\$pass_tiki='{$database->pass}';
\$dbs_tiki='{$database->dbname}';
\$client_charset = '{$charset}';
// generated by Tiki Manager {$date}
TXT;

        return $content;
    }

    public function getFilesToBackup()
    {
        if (! $this->vcs_instance || ! $this->vcs_instance->hasRemote($this->instance->webroot, $this->getBranch())) {
            return false;
        }

        $files = $this->getFileChanges();
        $backupFiles = \array_merge($files['changed'], $files['untracked']);
        $include = ['.git', '.svn'];

        return array_merge($backupFiles, $include);
    }

    /**
     * @return Git|Src|Svn
     */
    public function getVcsInstance()
    {
        return $this->vcs_instance;
    }

    public function getFileChanges($refresh = false)
    {

        static $files;

        if ($files && !$refresh) {
            return $files;
        }

        $files = [];
        $files['changed'] = $this->vcs_instance->getChangedFiles($this->instance->webroot);
        $files['untracked'] = $this->vcs_instance->getUntrackedFiles($this->instance->webroot, true);
        $files['deleted'] = $this->vcs_instance->getDeletedFiles($this->instance->webroot);

        // Git marks deleted files also as modified, this removes deleted files from the modified files.
        $files['changed'] = array_filter($files['changed'], function ($file) use ($files) {
            return !in_array($file, $files['deleted']);
        });

        $files['untracked'] = array_filter($files['untracked'], function ($path) {
            if (empty($path)) {
                return false;
            }

            foreach (self::$excludeBackupFolders as $excludeBackupFolder) {
                if (strpos($path, $excludeBackupFolder) === 0) {
                    return false;
                }
            }
            return true;
        });

        return $files;
    }

    public function getTikiRequirementsHelper()
    {
        return new TikiRequirementsHelper(new YamlFetcher());
    }

    /**
     * Set tiki preference
     *
     * @param string $prefName
     * @param string $prefValue
     * @return bool
     */
    public function setPref(string $prefName, string $prefValue): bool
    {
        $access = $this->instance->getBestAccess();

        if (!$this->instance->hasConsole() || !$access instanceof ShellPrompt) {
            return false;
        }

        try {
            $command = $access->createCommand(
                $this->instance->phpexec,
                ['-q', 'console.php', 'preferences:set', $prefName, $prefValue]
            );
            $command->run();
        } catch (CommandException $e) {
            return false;
        }

        $output = trim($command->getStdoutContent());

        return $command->getReturn() === 0 && preg_match('/was set.$/', $output);
    }

    /**
     * Get Tiki Preference
     *
     * @param string $prefName
     * @return false|mixed
     */
    public function getPref(string $prefName)
    {
        $access = $this->instance->getBestAccess();

        if (!$this->instance->hasConsole() || !$access instanceof ShellPrompt) {
            return false;
        }

        try {
            $command = $access->createCommand(
                $this->instance->phpexec,
                ['-q', 'console.php', 'preferences:get', $prefName]
            );
            $command->run();
        } catch (CommandException $e) {
            return false;
        }

        $output = trim($command->getStdoutContent());

        if ($command->getReturn() !== 0 || !preg_match('/has value (.*)$/', $output, $matches)) {
            return false;
        }

        return $matches[1];
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
