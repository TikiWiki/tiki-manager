<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use TikiManager\Access\FTP;
use TikiManager\Access\Mountable;
use TikiManager\Access\ShellPrompt;
use TikiManager\Application\Discovery\VirtualminDiscovery;
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
     * @param bool $indexMode
     * @return bool
     */
    public function backupDatabase(string $targetFile, bool $indexMode): bool
    {
        $access = $this->instance->getBestAccess('scripting');
        $indexMode = $indexMode ? 'include-index-backup' : 'skip-index-backup';

        if (!$access instanceof ShellPrompt || (ApplicationHelper::isWindows() && $this->instance->type == 'local')) {
            $data = $access->runPHP(
                dirname(__FILE__) . '/../../scripts/tiki/mysqldump.php',
                [$indexMode]
            );

            return (bool)file_put_contents($targetFile, $data);
        }

        $randomName = md5(time() . 'backup') . '.sql';
        $remoteFile = $this->instance->getWorkPath($randomName);

        $backupOutput = $access->runPHP(
            dirname(__FILE__) . '/../../scripts/tiki/backup_database.php',
            [$this->instance->webroot, $remoteFile, $indexMode]
        );
        // Note: runPHP currently does not return any error exit code.
        // So, we use a workaround: backup_database prints "DATABASE BACKUP OK" when everything succeeds.
        $backupSuccess = strpos($backupOutput, 'DATABASE BACKUP OK') !== false;

        if (!$backupSuccess) {
            trim_output($backupOutput, ['instance_id' => $this->instance->getId()]);
        }

        if ($backupSuccess) {
            $access->downloadFile($remoteFile, $targetFile);
        }

        if ($access->fileExists($remoteFile)) {
            $access->deleteFile($remoteFile);
        }

        return $backupSuccess && file_exists($targetFile);
    }

    public function beforeChecksumCollect()
    {
        $this->removeTemporaryFiles();
    }

    /**
     * @param Version $version
     * @param $folder
     */
    public function extractTo(Version $version, $folder, $revision = null): void
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
                $this->extractTo($version, $folder, $revision);
            }
        } else {
            $this->io->writeln('Cloning cache repository from server... <fg=yellow>[may take a while]</>');
            $this->vcs_instance->clone($version->branch, $folder);
        }

        if ($revision) {
            // Check if the revision exists, if not, deepen the clone
            if (! $this->vcs_instance->isRevisionPresent($folder, $revision)) {
                $this->io->writeln("<info>Deepening the clone to find revision {$revision}...</info>");
                $this->vcs_instance->deepenCloneUntilRevisionPresent($folder, $revision);
            }

            // checkout to that specific revision.
            $this->io->writeln("<info>Checking out to revision {$revision}...</info>");
            $this->vcs_instance->checkoutBranch($folder, $version->branch, $revision);
        }

        $this->io->writeln('Installing composer dependencies on cache repository... <fg=yellow>[may take a while]</>');
        $composerCmd = Process::fromShellCommandline("composer install -d $folder/vendor_bundled/ --no-interaction --prefer-dist --no-dev --quiet", null, null, null, 1800);
        $composerCmd->run();

        if ($composerCmd->getExitCode() !== 0) {
            $this->io->warning($composerCmd->getErrorOutput());
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
     * Get repository last revision date information
     *
     * @param $folder
     * @return mixed|string
     * @throws VcsException
     */
    public function getDateRevision($folder = null)
    {
        $date_revision = '';
        $commit_id = $this->getRevision($folder);

        if (strlen(trim($commit_id))>0) {
            $access = $this->instance->getBestAccess('scripting');
            $can_svn = $access->hasExecutable('svn') && $this->vcs_instance->getIdentifier() == 'SVN';
            $can_git = $access->hasExecutable('git') && $this->vcs_instance->getIdentifier() == 'GIT';

            if ($access instanceof ShellPrompt && ($can_git || $can_svn)) {
                $date_revision = $this->vcs_instance->getDateRevision($folder, $commit_id);
            }
        }

        return $date_revision;
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

                    // Define the php cli path to use
                    $extraParameters[] = '-p';
                    $extraParameters[] = $instance->phpexec;

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
        if (strpos($line, '[Warning]') !== false) {
            return true;
        }

        if (strpos($line, 'ERROR') !== false) {
            return true;
        }

        return false;
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

        $sourcefile = $_ENV['GIT_TIKIWIKI_FILE_ROOT'] . "/{$version->branch}/$filename";
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

    public function getLocalCheckouts()
    {
        $access = $this->instance->getBestAccess('scripting');
        $access->chdir($this->instance->webroot);
        $adjustedFindCmd = $access->executeWithPriorityParams('find');
        $command = $access->createCommand($adjustedFindCmd, ['.', '-type', 'd', '-name', '.git']);
        $command->run();

        $folders = [];
        $out = $command->getStdoutContent();
        foreach (explode("\n", $out) as $line) {
            if (trim($line) === "") {
                continue;
            }
            if (preg_match('/^\.\/(.*)\.git$/', trim($line), $m)) {
                if (strstr($m[1], 'vendor/')) {
                    continue;
                }
                if (empty($m[1])) {
                    $folders[] = 'tiki';
                } else {
                    $folders[] = $m[1];
                }
            }
        }

        return $folders;
    }

    /**
     * Install new instance.
     *
     * @param Version $version
     * @param bool $checksumCheck
     * @return null
     */
    public function install(Version $version, $checksumCheck = false, $revision = null)
    {
        $access = $this->instance->getBestAccess('scripting');
        $host = $access->getHost();

        $folder = cache_folder($this, $version);
        $this->extractTo($version, $folder, $revision);

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
                    'exclude' => ['.phpenv'],
                    'copy-errors' => $this->instance->copy_errors
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
        $this->installNodeJsDependencies();
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

    /**
     * Try to apply all instance defined patches
     */
    public function applyPatches()
    {
        $patches = Patch::getPatches($this->instance->getId());
        foreach ($patches as $patch) {
            try {
                $this->applyPatch($patch, [
                    'skip-reindex' => true,
                    'skip-cache-warmup' => true,
                    'skip-post-install' => true,
                ]);
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    /**
     * Applies a gitlab/github/maildir formatted patch to an instance
     * @throws Exception
     */
    public function applyPatch(Patch $patch, $options)
    {
        $access = $this->instance->getBestAccess('scripting');
        $vcsType = $this->vcs_instance->getIdentifier();
        $can_patch = $access->hasExecutable('patch');

        if (! $access instanceof ShellPrompt) {
            throw new \Exception('Access to this instance does not support execution of shell commands.');
        }

        if (! $can_patch) {
            throw new \Exception(sprintf('Patch utility is required to apply local patches.'));
        }

        $patch_contents = file_get_contents($patch->url);
        if (empty($patch_contents)) {
            throw new \Exception(sprintf('Unable to download patch contents from %s', $patch->url));
        }

        $local = tempnam($_ENV['TEMP_FOLDER'], 'patch');
        file_put_contents($local, $patch_contents);

        $filename = $this->instance->getWorkPath(basename($local));
        $access->uploadFile($local, $filename);

        if ($patch->package == 'tiki') {
            $access->chdir($this->instance->webroot);
        } else {
            // For packages installed via Tiki Package Manager
            $folder = 'vendor/';
            if (substr($patch->package, 0, strlen($folder)) === $folder) {
                $access->chdir($this->instance->getWebPath($patch->package));
            } else {
                $access->chdir($this->instance->getWebPath('vendor_bundled/vendor/'.$patch->package));
            }
        }

        $command = $access->createCommand('git apply', ['--stat'], $patch_contents);
        $command->run();
        $stat = $command->getReturn();

        $command = $access->createCommand('git apply', ['--check'], $patch_contents);
        $command->run();
        $check = $command->getReturn();
        $errorCheck = $command->getStderrContent();

        if ($stat !== 0 || $check !== 0) {
            $this->io->writeln("The patch cannot be applied, an error has been found.");
            if ($errorCheck) {
                $this->io->error($errorCheck);
            }
            if ($command->getReturn() === 128) {
                $this->io->writeln("Please provide a valid URL for the patch. (e.g. https://gitlab.com/tikiwiki/tiki/-/merge_requests/1374.patch)");
            }
            $result = false;
        } else {
            $command = $access->createCommand('patch', ['-R', '-p1', '-s', '-f', '--dry-run'], $patch_contents);
            $command->run();

            if ($command->getReturn() !== 0) {
                $command = $access->createCommand('patch', ['-p1', '-r-'], $patch_contents);
                $command->run();

                if ($info = $command->getStdoutContent()) {
                    $this->io->writeln($info);
                }
                if ($error = $command->getStderrContent()) {
                    $this->io->error($error);
                }
                $result = $command->getReturn() === 0;
            } else {
                $this->io->writeln("Patch already applied, skipping.");
                $result = false;
            }
        }

        $access->deleteFile($filename);
        @unlink($local);

        if ($result && empty($options['skip-post-install'])) {
            $this->io->writeln('Patch applied. Running post-install hooks...');
            $this->postInstall(array_merge($options, ['applying-patch' => true]));
        }
        return $result;
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
        $revision = $options['revision'] ?? null;

        if ($access instanceof ShellPrompt && ($can_git || $can_svn || $vcsType === 'SRC')) {
            $escaped_root_path = escapeshellarg(rtrim($this->instance->webroot, '/\\'));
            $escaped_temp_path = escapeshellarg(rtrim($this->instance->getWebPath('temp'), '/\\'));
            $escaped_cache_path = escapeshellarg(rtrim($this->instance->getWebPath('temp/cache'), '/\\'));

            if ($vcsType === 'SRC') {
                $version->branch = $this->vcs_instance->getBranchToUpdate($version->branch);
            }

            $lag = $options['lag'] ?? 0;
            $this->vcs_instance->update($this->instance->webroot, $version->branch, $lag, $revision);
            foreach ([$escaped_temp_path, $escaped_cache_path] as $path) {
                $script = sprintf('chmod(%s, 0777);', $path);
                $access->createCommand($this->instance->phpexec, ["-r {$script}"])->run();
            }
        } elseif ($access instanceof Mountable) {
            if ($revision) {
                $this->io->warning('Checking out a specific revision is not supported for non-Git repositories. Ignoring the revision argument.');
            }
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
        $revision = $options['revision'] ?? null;

        $access->getHost(); // trigger the config of the location change (to catch phpenv)

        if (!$access instanceof ShellPrompt ||  !($can_svn || $can_git || $this->vcs_instance->getIdentifier() == 'SRC')) {
            return;
        }

        $lag = $options['lag'] ?? 0;

        $this->clearCache();
        $this->moveVendor();
        $this->vcs_instance->update($this->instance->webroot, $version->branch, $lag, $revision);
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

    public function restoreDatabase(Database $database, string $remoteFile, bool $clone)
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

        if ($clone && $this->getPref('unified_elastic_index_current')) {
            // Remove current index information as it might match the source instance
            // And would delete the index upon a successful index rebuild
            $this->setPref('unified_elastic_index_current', '');
        }

        $this->postHook(__FUNCTION__);
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
            $access->shellExec("{$this->instance->phpexec} -q console.php database:install");
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

        $this->postHook(__FUNCTION__);
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
    public function getCompatibleVersions(bool $withBlank = true)
    {
        $versions = $this->getVersions();
        $checkTikiVersionRequirement = $this->getTikiRequirementsHelper();
        $compatible = [];
        foreach ($versions as $key => $version) {
            preg_match('/(\d+\.|trunk|master)/', $version->branch, $matches);
            if (!array_key_exists(0, $matches)) {
                // If is not a version formatted after a tiki version or master, then we can't guess if is compatible
                // we just add the version to the list (assuming is a custom branch and as such should be in the list)
                $compatible[$key] = $version;
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

    /**
     * @param Version $currentVersion
     * @param bool $onlySupported Display only supported versions
     * @return array
     */
    public function getUpgradableVersions(Version $currentVersion, bool $onlySupported): array
    {
        $instance = $this->instance;

        $app = $instance->getApplication();
        $versions = $onlySupported ? $app->getCompatibleVersions(false) : $app->getVersions();
        $branchVersion = $currentVersion->getBaseVersion();

        $options = [];

        foreach ($versions as $version) {
            $baseVersion = $version->getBaseVersion();

            $compatible = $baseVersion >= $branchVersion;
            $compatible |= $baseVersion === 'trunk';
            $compatible |= $baseVersion === 'master';

            if ($compatible) {
                $options[] = $version;
            }
        }

        return $options;
    }

    public function installComposer()
    {
        if ($this->instance->getBestAccess()->fileExists('temp/composer.phar')) {
            return;
        }

        $fs = new Filesystem();
        if (! empty($_ENV['COMPOSER_PATH'])) {
            $cacheFile = $_ENV['COMPOSER_PATH'];
            if (!$fs->exists($cacheFile)) {
                $cacheFile = Environment::get('CACHE_FOLDER') . '/composer_v1.phar';
            }
        }

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

        $command = $access->createCommand('bash', ['setup.sh', '-p', $instance->phpexec, 'composer']);
        if ($instance->type == 'local' && ApplicationHelper::isWindows()) {
            $command = $access->createCommand('composer', ['install', '-d vendor_bundled', '--no-interaction', '--prefer-dist', '--no-dev']);
        }

        $command->run();
        $commandOutput = $command->getStderrContent() ?: '';
        $commandOutput .= $command->getStdoutContent();

        $bundled =  $this->supportsVendorBundled() ? 'vendor_bundled/' : '';

        if ($command->getReturn() !== 0 ||
            !$access->fileExists($bundled . 'vendor/autoload.php') ||
            preg_match('/Your requirements could not be resolved/', $commandOutput)
        ) {
            trim_output($commandOutput, ['instance_id' => $instance->getId()]);
            throw new \Exception("Composer install failed for {$bundled}composer.lock (Tiki bundled packages).\nCheck logs in " . $_ENV['TRIM_LOGS'] . "/ for more details.");
        }

        if (preg_match('/Could not apply patch! Skipping./', $commandOutput)) {
            trim_output($commandOutput, ['instance_id' => $instance->getId()]);
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
            $errorMsg = "Failed to " . $action. " Tiki Packages listed in composer.json in the root folder.";
            $commandOutput = $command->getStderrContent() ?: $command->getStdoutContent() ?: $errorMsg;
            trim_output($commandOutput, ['instance_id' => $instance->getId()]);
            $this->io->error($errorMsg . "\nCheck logs in " . $_ENV['TRIM_LOGS'] . "/ for more details.");
        }
    }

    /**
     * Runs NPM install and build
     * @return void
     */
    public function installNodeJsDependencies()
    {
        if (! $this->supportsNodeJSBuild()) {
            return;
        }

        $instance = $this->instance;
        $access = $instance->getBestAccess('scripting');

        if (! $access instanceof ShellPrompt || ! $instance->hasConsole()) {
            return;
        }

        if (! $access->hasExecutable('npm')) {
            $this->io->writeln('Command "npm" not found, skipping installing NPM packages...');
            return;
        }

        $access->chdir($instance->webroot);

        $this->io->writeln('Installing NPM dependencies... <fg=yellow>[may take a while]</>');

        $command = $access->createCommand('npm', ['install', '--clean-install', '--engine-strict']);

        $command->run();
        $commandOutput = $command->getStderrContent() ?: '';
        $commandOutput .= $command->getStdoutContent();

        trim_output($commandOutput);

        if ($command->getReturn() !== 0) {
            throw new \Exception("NPM install failed.\nCheck logs in " . $_ENV['TRIM_LOGS'] . "/ for more details.");
        }

        $this->io->writeln('NPM, Building artifacts (JS/CSS)... <fg=yellow>[may take a while]</>');
        $command = $access->createCommand('npm', ['run', 'build']);

        $command->run();
        $commandOutput = $command->getStderrContent() ?: '';
        $commandOutput .= $command->getStdoutContent();

        trim_output($commandOutput);

        if ($command->getReturn() !== 0) {
            throw new \Exception("NPM build failed.\nCheck logs in " . $_ENV['TRIM_LOGS'] . "/ for more details.");
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

        $errorMsg = "Failed to update Tiki Packages.\nCheck logs in " . $_ENV['TRIM_LOGS'] . "/ for more details.";
        $command->run();

        $commandOutput = $command->getStderrContent() ?: $command->getStdoutContent();

        // If the command do not support the --handle-deprecated option
        if ($command->getReturn() !== 0 && preg_match('/option does not exist/', $commandOutput)) {
            return false;
        }

        if ($command->getReturn() !== 0) {
            trim_output($commandOutput, ['instance_id' => $this->instance->getId()]);
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

        if (empty($options['applying-patch'])) {
            $this->applyPatches();
        }

        if ($this->vcs_instance->getIdentifier() != 'SRC') {
            $this->installComposer();
            $this->installComposerDependencies();
            $this->installNodeJsDependencies();
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

    protected function supportsNodeJSBuild(): bool
    {
        // Build system with NodeJS was introduces for 27.x
        $baseVersion = $this->instance->getApplication()->getBaseVersion();

        return $baseVersion >= 27 || $baseVersion == 'master' || $baseVersion == 'trunk';
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
                ['-q', 'console.php', 'database:update']
            );
            $command->run();
            if ($command->getReturn() !== 0) {
                $message = 'Failed to update database. For more information check the logs or access instance and run `php console.php database:update`.';
                App::get('io')->writeln('<error>' . $message . '</error>');
                debug($command->getStdoutContent(), $this->instance->name);
                debug($command->getStderrContent(), $this->instance->name);
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

    public function getTikiRequirementsHelper(): TikiRequirementsHelper
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

    public function hook($hookName): void
    {
        if (method_exists($this, $hookName)) {
            $this->$hookName();
        }
    }

    public function postHook($methodName): void
    {
        $this->hook('post' . ucfirst($methodName));
    }

    /**
     * Hook to perform changes after database setup/install
     *
     * @return void
     */
    protected function postSetupDatabase(): void
    {
        $discovery = $this->instance->getDiscovery();

        if ($discovery instanceof VirtualminDiscovery) {
            $tikiTmpDir = $discovery->detectTmp();
            if (preg_match('/^\/home\/.*\/tmp$/', $tikiTmpDir)) {
                $this->setPref('tmpDir', $tikiTmpDir);
            }
        }
    }

    /**
     * Hook to perform changes after database restore
     *
     * @return void
     */
    protected function postRestoreDatabase(): void
    {
        $discovery = $this->instance->getDiscovery();

        if ($this->getPref('tmpDir') && $discovery instanceof VirtualminDiscovery) {
            $tikiTmpDir = $discovery->detectTmp();
            if (preg_match('/^\/home\/.*\/tmp$/', $tikiTmpDir)) {
                $this->setPref('tmpDir', $tikiTmpDir);
            }
        }
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
