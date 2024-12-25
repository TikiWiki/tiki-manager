<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Application\Exception\FolderPermissionException;
use TikiManager\Config\App;
use TikiManager\Access\Access;
use TikiManager\Libs\VersionControl\Svn;
use TikiManager\Libs\Helpers\ApplicationHelper;
use TikiManager\Libs\VersionControl\VersionControlSystem;
use TikiManager\Application\Exception\BackupCopyException;
use TikiManager\Config\Environment;

class Backup
{
    public const FULL_BACKUP = 'full';
    public const PARTIAL_BACKUP = 'partial';

    /** @var SymfonyStyle */
    protected $io;

    /** @var Access $access */
    protected $access;
    protected $app;
    protected $archiveDir;
    protected $archiveRoot;
    protected $backupDir;
    protected $backupDirname;
    protected $backupRoot;
    protected $errors;
    protected $instance;
    protected $workpath;
    protected $direct;
    protected $onlyCode;
    protected $full;
    protected $excludeList;
    protected $indexMode;

    /**
     * Backup constructor.
     * @param $instance
     * @param bool $direct
     * @param bool $full
     * @param bool $onlyCode
     * @param array $excludeList
     * @param bool $indexMode
     * @throws FolderPermissionException
     */
    public function __construct($instance, $direct = false, $full = true, $onlyCode = false, $excludeList = [], $indexMode = true)
    {
        $this->setIO(App::get('io'));

        $this->instance = $instance;
        $this->access = $this->getAccess($instance);
        $this->app = $instance->getApplication();
        $this->workpath = $instance->createWorkPath($this->access);
        $this->archiveRoot = rtrim($_ENV['ARCHIVE_FOLDER'], DIRECTORY_SEPARATOR);
        $this->backupRoot = rtrim($_ENV['BACKUP_FOLDER'], DIRECTORY_SEPARATOR);
        $this->backupDirname = sprintf('%s-%s', $instance->id, $instance->name);
        $this->backupDir = $this->backupRoot . DIRECTORY_SEPARATOR . $this->backupDirname;
        $this->archiveDir = $this->archiveRoot . DIRECTORY_SEPARATOR . $this->backupDirname;
        $this->direct = $direct;
        $this->onlyCode = $onlyCode;
        $this->errors = [];
        $this->full = !in_array($instance->vcs_type, ['git', 'svn']) ? true : $full;
        $this->indexMode = $indexMode;

        $this->createBackupDir();
        $this->createArchiveDir();
        $this->setExclusionList($excludeList);
    }

    public function setExclusionList(array $excludeList)
    {
        $this->excludeList = $excludeList;
    }

    public function resetExclusionList()
    {
        $this->excludeList = [];
    }

    public function copyDirectories($targets, $backupDir)
    {
        $access = $this->getAccess();
        $backupDir = $backupDir ?: $this->backupDir;
        $result = [];

        $fileSystem = new Filesystem();

        foreach ($targets as $target) {
            if ($this->direct) {
                return $access->localizeFolder($target, $backupDir);
            } else {
                list($type, $dir) = $target;
                $hash = md5($dir);
                $destDir = $backupDir . DIRECTORY_SEPARATOR . $hash;
                if ($type == 'app' && !$this->full) {
                    $files = $this->app->getFilesToBackup();
                    $dirTmp = $this->createTempPartial($dir, $files);
                    $error_code = $access->localizeFolder($dirTmp, $destDir);
                    $fileSystem->remove([dirname($dirTmp)]);
                } else {
                    $error_code = $access->localizeFolder($dir, $destDir);
                }

                if ($error_code) {
                    if (array_key_exists($error_code, $this->errors)) {
                        $this->errors[$error_code][] = $dir;
                    } else {
                        $this->errors[$error_code] = [$error_code => $dir];
                    }
                } else {
                    if ($this->instance->vcs_type == 'svn') {
                        /** @var Svn $svn */
                        $svn = VersionControlSystem::getVersionControlSystem($this->instance);
                        $svn->ensureTempFolder($destDir . DIRECTORY_SEPARATOR . basename($dir));
                    }
                }

                $result[] = [
                    $hash,
                    $type,
                    $dir,
                    $this->full ? Backup::FULL_BACKUP : Backup::PARTIAL_BACKUP
                ];
            }
        }

        if (!empty($this->errors)) {
            throw new BackupCopyException(
                $this->errors,
                BackupCopyException::RSYNC_ERROR
            );
        }

        return $result;
    }

    public function create($skipArchive = false, $backupDir = null)
    {
        $backupDir = $backupDir ?: $this->backupDir;

        $this->io->writeln('Checking directories...');
        $targets = $this->getTargetDirectories();

        $copyResult = $targets;

        if (!$this->direct) {
            $this->io->writeln('Copying files... <fg=yellow>[may take a while]</>');
            $copyResult = $this->copyDirectories($targets, $backupDir);

            $this->io->writeln('Creating changes file...');
            $this->createChangesFile($backupDir);
        }

        $this->io->writeln('Checking system ini config file...');
        $targetSystemIniConfigFile = $this->getSystemIniConfigFile();
        if (!empty($targetSystemIniConfigFile)) {
            $parts = explode('||', $targetSystemIniConfigFile);
            if (isset($parts[0]) && isset($parts[1])) {
                $targetSystemIniConfigFile = $parts[0];

                if ($parts[1] == 'external') {
                    $this->io->writeln('Downloading system ini config file...');
                }

                $this->copySystemIniConfigFile($targetSystemIniConfigFile, $backupDir, $copyResult, $parts[1]);
            }
        }

        $this->io->writeln('Creating manifest...');
        $this->createManifest($copyResult, $backupDir);

        if (! $this->onlyCode) {
            $this->io->writeln('Creating database dump...');
            $this->createDatabaseDump($this->app, $backupDir);
        }

        $result = $backupDir;
        if (!$skipArchive || !$this->direct) {
            $this->io->writeln('Creating archive... <fg=yellow>[may take a while]</>');
            $result = $this->createArchive();
        }

        return !$result ? false : $result;
    }

    public function createArchive($archiveDir = null)
    {
        $archiveDir = $archiveDir ?: $this->archiveDir;

        $bzipStep = false;

        // If its windows we need to tar first and then bzip2 the tar
        if (ApplicationHelper::isWindows()) {
            $bzipStep = true;
        }

        $fileName = sprintf('%s_%s.tar%s', $this->backupDirname, date('Y-m-d_H-i-s'), $bzipStep ? '' : '.bz2');
        $tarPath = $archiveDir . DIRECTORY_SEPARATOR . $fileName;

        $exclude_command = '';
        if (!empty($this->excludeList)) {
            foreach ($this->excludeList as $exclude) {
                $exclude_command .= " --exclude='" . $exclude['exclude'] . "'";
            }
        }

        $command = sprintf(
            "tar%s -cp%s -C %s -f %s %s",
            $exclude_command,
            $bzipStep ? '' : 'j',
            escapeshellarg($this->backupRoot),
            escapeshellarg($tarPath),
            escapeshellarg($this->backupDirname)
        );

        $command = $this->access->executeWithPriorityParams($command);

        exec($command, $output, $return_var);

        if ($return_var != 0) {
            $this->io->error("TAR exit code: $return_var");
        }

        if (!$bzipStep) {
            $success = $return_var === 0
                && file_exists($tarPath)
                && filesize($tarPath) > 0;

            return $success ? $tarPath : false;
        }

        $command = sprintf('bzip2 %s', $tarPath);
        $command = $this->access->executeWithPriorityParams($command);
        exec($command, $output, $return_var);

        $tarPath .= '.bz2';
        $success = $return_var === 0
            && file_exists($tarPath)
            && filesize($tarPath) > 0;

        return $success ? $tarPath : false;
    }

    /**
     * @param null $archiveDir
     * @return bool|string|null
     * @throws FolderPermissionException
     */
    public function createArchiveDir($archiveDir = null)
    {
        $archiveDir = $archiveDir ?: $this->archiveDir;

        return $this->createDir($archiveDir);
    }

    /**
     * @param null $backupDir
     * @return string|null
     * @throws FolderPermissionException
     */
    public function createBackupDir($backupDir = null)
    {
        $backupDir = $backupDir ?: $this->backupDir;

        return $this->createDir($backupDir);
    }

    protected function createDir($folder)
    {
        $parentFolder = dirname($folder);

        $exceptionMessage = 'Folder "%s" is not writable. Tiki-manager requires write privileges in order to create backups.';

        if (!is_writable($parentFolder) && !$this->fixPermissions($parentFolder)) {
            throw new FolderPermissionException(sprintf($exceptionMessage, $parentFolder));
        }

        if (is_dir($folder) || mkdir($folder, $this->getFilePerm(), true)) {
            if (!$this->fixPermissions($folder)) {
                throw new FolderPermissionException(sprintf($exceptionMessage, $folder));
            }

            return $folder;
        }
        return false;
    }

    public function createDatabaseDump($app, $backupDir = null)
    {
        $app = $app ?: $this->app;
        $backupDir = $backupDir ?: $this->backupDir;
        $sqlpath = $backupDir . DIRECTORY_SEPARATOR . 'database_dump.sql';

        file_exists($sqlpath) && unlink($sqlpath);

        if (!$app->backupDatabase($sqlpath, $this->indexMode)) {
            throw new \RuntimeException('Unsuccessful database backup. Aborting. See log files for more information.');
        }

        if (file_exists($sqlpath)) {
            $this->fixPermissions($sqlpath);
            return $sqlpath;
        }

        return false;
    }

    public function createManifest($data, $backupDir = null)
    {
        $backupDir = $backupDir ?: $this->backupDir;
        $manifestFile = $backupDir . DIRECTORY_SEPARATOR . 'manifest.txt';
        $file = fopen($manifestFile, 'w');
        $lineTemplate = !$this->direct ? '%s    %s    %s    %s' : '%s    %s';

        foreach ($data as $location) {
            if ($line = @vsprintf($lineTemplate . PHP_EOL, $location)) {
                fwrite($file, $line);
            } else {
                $this->io->warning("Failed to generate manifest entry line");
                $error = "Invalid/insufficient data to generate the manifest:\n" .
                    "Received " . count($data) . " arguments instead of " . (!$this->direct ? 4 : 2) . "\n" .
                    var_export($data, true);
                debug($error, null, "\n\n");
            }
        }

        fclose($file);
        $this->fixPermissions($manifestFile);
        return $manifestFile;
    }

    public function createChangesFile($backupDir = null)
    {
        list('changed' => $changes, 'untracked' => $untracked, 'deleted' => $deleted) = $this->app->getFileChanges();

        $backupDir = $backupDir ?: $this->backupDir;

        $changesFile = $backupDir . '/changes.txt';
        $fileStream = fopen($changesFile, 'w');
        $lineTemplate = '%s    %s';

        foreach ($changes as $file) {
            $line = sprintf($lineTemplate . PHP_EOL, 'M', $file);
            fwrite($fileStream, $line);
        }

        foreach ($untracked as $file) {
            $line = sprintf($lineTemplate . PHP_EOL, 'A', $file);
            fwrite($fileStream, $line);
        }

        foreach ($deleted as $file) {
            $line = sprintf($lineTemplate . PHP_EOL, 'D', $file);
            fwrite($fileStream, $line);
        }

        fclose($fileStream);
        $this->fixPermissions($changesFile);
        return $changesFile;
    }

    /**
     * Fix folder permissions based on current backup properties
     * @param $path
     * @return bool
     */
    public function fixPermissions($path)
    {
        $filesystem = new Filesystem();
        $perm = $this->getFilePerm();
        $user = $this->getFileUser();
        $group = $this->getFileGroup();
        $errors = [];

        if (is_dir($path)) {       // avoid rw-rw-rw- for dirs
            $perm = (($perm & 0b100100100) >> 2) | $perm;
        } elseif (is_file($path)) { // avoid --x--x--x for files
            $perm = ($perm & 0b001001001) ^ $perm;
        }

        if ($perm) {
            try {
                $filesystem->chmod($path, $perm);
            } catch (IOException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!is_null($group)) {
            try {
                $filesystem->chgrp($path, $group);
            } catch (IOException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!is_null($user)) {
            try {
                $filesystem->chown($path, $user);
            } catch (IOException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors) {
            $this->io->error(implode(PHP_EOL, $errors));
            return false;
        }

        return true;
    }

    public function getAccess($instance = null): Access
    {
        $instance = $instance ?: $this->instance;

        return $instance->getBestAccess();
    }

    public function getArchives($archiveRoot = null, $instance = null)
    {
        $archiveRoot = $archiveRoot ?: $this->archiveRoot;
        $instance = $instance ?: $this->instance;

        $globPattern = implode(DIRECTORY_SEPARATOR, [
            $archiveRoot,
            $instance->id . '-*',
            $instance->id . '*_*.tar.bz2',
        ]);
        return array_reverse(glob($globPattern));
    }

    public function getBackupDir()
    {
        return $this->backupDir;
    }

    public function getTargetDirectories()
    {
        $targets = [];
        $extraBackups = $this->instance->getExtraBackups() ?: [];
        $locations = $this->app->getFileLocations();

        foreach ($locations as $type => $directories) {
            foreach ($directories as $dir) {
                $targets[] = [$type, $dir];
            }
        }

        foreach ($extraBackups as $dir) {
            $targets[] = ['data', $dir];
        }

        return $targets;
    }

    /**
     * Get system ini config file path
     *
     * @return mixed
     */
    public function getSystemIniConfigFile()
    {
        return $this->app->getSystemIniConfigFilePath();
    }

    /**
     * Copy system ini config file to backup folder
     *
     * @param $path
     * @param $backupDir
     * @param $copyResult
     * @param $location
     */
    public function copySystemIniConfigFile($path, $backupDir, &$copyResult, $location)
    {
        $backupDir = $backupDir ?: $this->backupDir;

        $file = basename($path);

        if ($location == 'external') {
            $filePath = $path;
            if ($path[0] !== DIRECTORY_SEPARATOR) {
                $webroot = rtrim($this->instance->webroot, DIRECTORY_SEPARATOR);
                $filePath = $webroot . DIRECTORY_SEPARATOR . $path;
                $filePath = ApplicationHelper::getAbsolutePath($filePath);
            }

            $access = $this->getAccess();
            $access->downloadFile($filePath, $backupDir);
        }

        $copyResult[] = $this->direct ? ['conf_' . $location, $path] : [$file, 'conf_' . $location, $path, null];
    }

    public function setArchiveSymlink($symlinkPath = null, $archiveDir = null, $instance = null)
    {
        if (file_exists($symlinkPath)) { // if destination path exists, skip
            return true;
        }

        $archiveDir = $archiveDir ?: $this->archiveDir;
        $instance = $instance ?: $this->instance;
        $symlinkPath = $symlinkPath ?: dirname($instance->webroot) . DIRECTORY_SEPARATOR . 'backup';

        // If Tiki Manager archive dir is a link, swap link and target
        if (is_link($archiveDir)) {
            $realArchiveDir = readlink($archiveDir);
            unlink($archiveDir);
            if (file_exists($realArchiveDir)) {
                rename($realArchiveDir, $archiveDir);
            } else {
                mkdir($archiveDir, $this->getFilePerm(), true);
            }
        }

        symlink($archiveDir, $symlinkPath);
        $success = is_dir($archiveDir)
            && is_link($symlinkPath)
            && readlink($symlinkPath) === $archiveDir;

        $this->fixPermissions($archiveDir);
        return $success;
    }

    private function createTempPartial($root, $files)
    {
        $fileSystem = new Filesystem();
        $temp = implode(
            \DIRECTORY_SEPARATOR,
            [Environment::get('TEMP_FOLDER'), md5(time()), basename($this->instance->webroot)]
        );
        foreach ($files as $file) {
            $dest = $temp . DIRECTORY_SEPARATOR . $file;
            $src = $root . DIRECTORY_SEPARATOR . $file;
            if (!$fileSystem->exists($src)) {
                continue;
            }
            $fileSystem->mkdir(dirname($dest));
            if (is_dir($src)) {
                $fileSystem->mirror($src, $dest);
            } else {
                $fileSystem->copy($src, $dest, true);
            }
        }

        return $temp;
    }

    public function getFilePerm()
    {
        return intval($this->instance->getProp('backup_perm')) ?: 0770;
    }

    public function getFileUser()
    {
        return $this->instance->getProp('backup_user');
    }

    public function getFileGroup()
    {
        return $this->instance->getProp('backup_group');
    }

    public function setIO(SymfonyStyle $io)
    {
        $this->io = $io;
    }
}
