<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application;

use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Application\Exception\RestoreErrorException;
use TikiManager\Config\Environment;
use TikiManager\Libs\Helpers\ApplicationHelper;
use TikiManager\Libs\VersionControl\Git;
use TikiManager\Libs\VersionControl\Svn;

class Restore extends Backup
{
    const CLONE_PROCESS = 'clone';
    const RESTORE_PROCESS = 'restore';

    protected $restoreRoot;
    protected $restoreDirname;
    protected $restoreLockFile;
    protected $process;
    protected $source;
    public $iniFilesToExclude = [];

    /**
     * Restore constructor.
     * @param Instance $instance
     * @param bool $direct
     * @throws Exception\FolderPermissionException
     */
    public function __construct(Instance $instance, bool $direct = false)
    {
        parent::__construct($instance, $direct);
        $this->restoreRoot = $instance->tempdir . DIRECTORY_SEPARATOR . 'restore';
        $this->restoreDirname = sprintf('%s-%s', $instance->getId(), $instance->name);
        $this->restoreLockFile = $instance->tempdir . DIRECTORY_SEPARATOR . 'restore.lock';
    }

    public function lock()
    {
        if ($this->access->fileExists($this->restoreLockFile)) {
            $script = sprintf("echo filemtime('%s');", $this->restoreLockFile);
            $command = $this->access->createCommand($this->instance->phpexec, ["-r {$script}"]);
            $command->run();

            $modTimestamp = trim($command->getStdoutContent());

            if ($modTimestamp &&
                is_numeric($modTimestamp) &&
                strtotime('+30 minutes', (int)$modTimestamp) > time()
            ) {
                throw new RestoreErrorException(
                    "Restore lock file found in '$this->restoreLockFile', check if there is another restore in progress or delete this file if you are not able to proceed.",
                    RestoreErrorException::LOCK_ERROR
                );
            }
        }

        $tempDir = Environment::get('TEMP_FOLDER');
        $tempLock = $tempDir . DS . 'restore.lock';

        touch($tempLock);
        $this->access->uploadFile($tempLock, $this->restoreLockFile);
        unlink($tempLock);
    }

    public function unlock()
    {
        $this->access->deleteFile($this->restoreLockFile);
    }

    public function getFolderNameFromArchive($srcArchive)
    {
        $bf = bzopen($srcArchive, 'r');
        $char = bzread($bf, 1);
        $content = $char;

        while (!feof($bf)) {
            $char = bzread($bf, 1);
            if ($char === "\0") {
                break;
            }
            $content .= $char;
        }
        bzclose($bf);

        $content = trim($content, '/');
        return $content;
    }

    public function getRestoreFolder()
    {
        return $this->getRestoreRoot() . DIRECTORY_SEPARATOR . $this->restoreDirname;
    }

    public function getRestoreRoot()
    {
        return rtrim($this->restoreRoot, '/\\');
    }

    /**
     * @param $srcArchive
     * @return string
     * @throws RestoreErrorException
     */
    protected function prepareArchiveFolder($srcArchive)
    {
        $access = $this->access;
        $instance = $this->instance;
        $archivePath = $srcArchive;
        $archiveRoot = $this->getRestoreRoot();

        if ($instance->type !== 'local') {
            $archivePath = $this->uploadArchive($srcArchive);
        }

        if (!$access->fileExists($archiveRoot)) {
            $this->createRestoreRootFolder($archiveRoot);
        }

        $this->restoreDirname = $this->getFolderNameFromArchive($srcArchive);
        $restoreFolder =  $this->getRestoreFolder();

        // If the restore folder exists, remove to avoid decompression issues;
        if ($access->fileExists($restoreFolder)) {
            $this->removeRestoreFolder();
        }

        $this->decompressArchive($archiveRoot, $archivePath);

        return $restoreFolder;
    }

    public function createRestoreRootFolder($archiveRoot)
    {
        $path = $this->access->getInterpreterPath();
        $script = sprintf("echo mkdir('%s', 0777, true);", $archiveRoot);
        $command = $this->access->createCommand($path, ["-r {$script}"]);
        $command->run();

        if (empty($command->getStdoutContent())) {
            throw new RestoreErrorException(
                "Can't create '$archiveRoot': "
                . $command->getStderrContent(),
                RestoreErrorException::CREATEDIR_ERROR
            );
        }
    }

    public function readManifest($manifestPath)
    {
        $access = $this->getAccess();

        if ($this->direct && $this->source->type == 'local') {
            $access = $this->source->getBestAccess();
        }

        $webroot = rtrim($this->instance->webroot, '/\\');

        $archiveFolder = dirname($manifestPath);
        $manifest = $access->fileGetContents($manifestPath);
        $manifest = explode(PHP_EOL, $manifest);
        $manifest = array_map('trim', $manifest);
        $manifest = array_filter($manifest, 'strlen');
        $backupType = Backup::FULL_BACKUP;

        $windowsAbsolutePathsRegex = '/^([a-zA-Z]\:[\/,\\\\]).{1,}/';

        $folders = [];
        if (empty($manifest)) {
            throw new RestoreErrorException(
                "Manifest file is invalid: '{$manifestPath}'",
                RestoreErrorException::MANIFEST_ERROR
            );
        }

        foreach ($manifest as $line) {
            $values = explode('    ', $line);
            switch (count($values)) {
                case 2:
                    list($type, $destination) = $values;
                    break;
                case 3:
                    list($hash, $type, $destination) = $values;
                    break;
                case 4:
                    list($hash, $type, $destination, $backupType) = $values;
                    break;
            }

            if ($type == 'conf_local') {
                continue;
            }

            if ($this->direct) {
                $source = ($type === 'app') ? $destination : $this->getSourceInstance()->getWebPath($destination);
            } else {
                $source = $archiveFolder . DIRECTORY_SEPARATOR . $hash;
                $source .= $type != 'conf_external' ? DIRECTORY_SEPARATOR . basename($destination) : '';
            }

            $windowsAbsolutePaths = (preg_match($windowsAbsolutePathsRegex, $destination, $matches)) ? true : false;

            if ($destination{0} === '/' || $windowsAbsolutePaths) {
                if ($type === 'app') {
                    $destination = '';
                } else {
                    $this->io->warning("{Skipping {$destination}. Path shouldn't have absolute paths, to avoid override data.");
                    continue;
                }
            }

            $destination = $webroot . DIRECTORY_SEPARATOR . $destination;
            $destination = ApplicationHelper::getAbsolutePath($destination);

            $folders[] = [
                $type,
                $source,
                $destination,
                $backupType == Backup::FULL_BACKUP,
            ];
        }
        return $folders;
    }

    /**
     * @param string $srcContent
     * @throws RestoreErrorException
     */
    public function restoreFiles(string $srcContent)
    {
        if (is_dir($srcContent)) {
            $this->restoreFilesFromFolder($srcContent);
        } elseif (is_file($srcContent)) {
            $this->restoreFilesFromArchive($srcContent);
        }
    }

    /**
     * @param string $srcArchive
     * @throws RestoreErrorException
     */
    protected function restoreFilesFromArchive(string $srcArchive)
    {
        $srcFolder = $this->prepareArchiveFolder($srcArchive);
        return $this->restoreFilesFromFolder($srcFolder);
    }

    protected function restoreFilesFromFolder(string $srcFolder)
    {
        $manifest = "{$srcFolder}/manifest.txt";
        $folders = $this->readManifest($manifest);

        $this->setIniFilesToExclude($manifest);

        foreach ($folders as $folder) {
            list($type, $src, $target, $isFull) = $folder;

            // system configuration file
            if ($type == 'conf_external') {
                if ($this->isSSHToLocal()) {
                    $this->getSourceInstance()->getBestAccess()->downloadFile($src, $target);
                } else {
                    $this->getAccess()->uploadFile($src, $target);
                }

                continue;
            }

            if ($type == 'app' && !$isFull) {
                $this->restoreFromVCS($src, $target);
            }

            $this->restoreFolder($src, $target, $isFull);
        }

        $changes = "{$srcFolder}/changes.txt";
        $this->applyChanges($changes);
    }

    public function restoreFolder($src, $target, $isFull = false)
    {
        $access = $this->getAccess();
        $instance = $this->instance;
        $src = rtrim($src, '/\\');
        $target = rtrim($target, '/\\');

        if (empty($src) || empty($target)) {
            throw new RestoreErrorException(
                "Invalid paths:\n \$src='$src';\n \$target='$target';",
                RestoreErrorException::INVALID_PATHS
            );
        }

        $path = $this->access->getInterpreterPath();
        $script = sprintf("if (!is_dir('%s')) { echo mkdir('%s', 0777, true); };", $target, $target);

        $command = $access->createCommand($path, ["-r {$script}"]);
        $command->run();

        if ($command->getReturn() !== 0) {
            throw new RestoreErrorException(
                "Can't create target folder '$target': "
                . $command->getStderrContent(),
                RestoreErrorException::CREATEDIR_ERROR
            );
        }

        if (ApplicationHelper::isWindows() && $instance->type == 'local') {
            $toExclude = [
                $src . DIRECTORY_SEPARATOR . '.htaccess',
                $src . DIRECTORY_SEPARATOR . 'maintenance.php',
                $src . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'local.php',
            ];

            if ($this->direct) {
                $toExclude[] = $src . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . '*';
            }

            if ($this->getProcess() == self::CLONE_PROCESS && !empty($this->iniFilesToExclude)) {
                foreach ($this->iniFilesToExclude as $iniFile) {
                    $toExclude[] = $src . DIRECTORY_SEPARATOR . $iniFile;
                }
            }

            $host = $this->access->getHost();
            $returnVal = $host->windowsSync(
                $src,
                $target,
                null,
                $toExclude
            );

            if ($returnVal > 8) {
                throw new RestoreErrorException(
                    "Failed copying '$src' to '$target': Robocopy error code " . $returnVal,
                    RestoreErrorException::COPY_ERROR
                );
            }

            if ($access->fileExists($src . '/.htaccess')) {
                $host->sendFile(
                    $src . DIRECTORY_SEPARATOR . '.htaccess',
                    $target . DIRECTORY_SEPARATOR . '.htaccess' . ($instance->isLocked() ? '.bak' : '')
                );
            }
        } else {
            $rsyncFlags = [
                '-a',
                $isFull ? '--delete' : '--force'
            ];

            $rsyncExcludes = [
                '--exclude',
                '/.htaccess',
                '--exclude',
                '/maintenance.php',
                '--exclude',
                '/db/local.php'
            ];

            if ($this->direct) {
                // Sync options for the temp folder
                $rsyncExcludes = array_merge($rsyncExcludes, [
                    '--include=temp/**/',
                    '--include=.gitkeep',
                    '--include=index.php',
                    '--include=.htaccess',
                    '--include=README',
                    '--exclude=temp/**/*',
                    '--exclude=temp/*',
                ]);
            }

            if ($this->getProcess() == self::CLONE_PROCESS && !empty($this->iniFilesToExclude)) {
                foreach ($this->iniFilesToExclude as $iniFile) {
                    $rsyncExcludes[] = '--exclude';
                    $rsyncExcludes[] = $iniFile;
                }
            }

            $accessToRestore = $access;

            if ($localToSSH = $this->isLocalToSSH()) {
                $sshPort = $access->port;
                $target = $access->getRsyncPrefix() . $target;
                $access = $this->source->getBestAccess();
            }

            if ($sshToLocal = $this->isSSHToLocal()) {
                $sourceAccess = $this->getSourceInstance()->getBestAccess();
                $sshPort = $sourceAccess->port;
                $rsyncPrefix = $sourceAccess->getRsyncPrefix();
                $src = $rsyncPrefix . $src;
            }

            if ($localToSSH || $sshToLocal) {
                $rsyncFlags[] = '-e';
                $rsyncFlags[] = 'ssh -p ' . ($sshPort ?? 22) .' -i ' . Environment::get('SSH_KEY');
            }

            $rsyncFolders = [
                $src . '/',
                $target . '/'
            ];

            $rsyncContent = array_merge($rsyncFlags, $rsyncExcludes, $rsyncFolders);

            $command = $access->createCommand('rsync');
            $command->setArgs(
                $rsyncContent
            );
            $command->run();

            if ($command->getReturn() !== 0) {
                throw new RestoreErrorException(
                    "Failed copying '$src' to '$target': "
                    . $command->getStderrContent(),
                    RestoreErrorException::COPY_ERROR
                );
            }

            $access = $accessToRestore;

            if ($access->fileExists($src . '/.htaccess')) {
                $command = $access->createCommand('rsync');
                $command->setArgs([
                    $src . '/.htaccess',
                    $target . '/.htaccess' . ($instance->isLocked() ? '.bak' : '')
                ]);
                $command->run();
            }
        }

        return true;
    }

    public function uploadArchive($srcArchive)
    {
        $access = $this->access;
        $instance = $this->instance;

        $basename = basename($srcArchive);
        $remote = $instance->getWorkPath($basename);
        $access->uploadFile($srcArchive, $remote);
        return $remote;
    }

    /**
     * Decompress a bzip2 file into a given folder
     *
     * @param $folder
     * @param $archive
     * @throws RestoreErrorException
     */
    public function decompressArchive($folder, $archive)
    {
        $access = $this->access;

        $bzipStep = false;
        $tarFlags = '-xpj';
        if (ApplicationHelper::isWindows()) {
            $bzipStep = true;
            $tarFlags = '-xp';
            $archive = str_replace('/', DIRECTORY_SEPARATOR, $archive);
        }

        if ($bzipStep) {
            $args = ['-dk', $archive];
            $command = $access->createCommand('bzip2', $args);
            $command->run();

            if ($command->getReturn() !== 0) {
                throw new RestoreErrorException(
                    "Can't extract '$archive' to '$folder': "
                    . $command->getStderrContent(),
                    RestoreErrorException::DECOMPRESS_ERROR
                );
            }

            $archive = preg_replace('/.bz2$/', '', $archive);
        }

        $args = [$tarFlags, '-C', $folder, '-f', $archive];
        $command = $access->createCommand('tar', $args);
        $command->run();

        if ($command->getReturn() !== 0) {
            throw new RestoreErrorException(
                "Can't extract '$archive' to '$folder': "
                . $command->getStderrContent(),
                RestoreErrorException::DECOMPRESS_ERROR
            );
        }

        if ($bzipStep && file_exists($archive)) {
            unlink($archive);
        }
    }

    /**
     * @param $process
     */
    public function setProcess($process)
    {
        $this->process = $process ? self::CLONE_PROCESS : self::RESTORE_PROCESS;
    }

    /**
     * @return mixed
     */
    public function getProcess()
    {
        return $this->process;
    }

    /**
     * Set the source associated to this restore instance
     * @param Instance $source
     */
    public function setSourceInstance(Instance $source): void
    {
        $this->source = $source;
    }

    /**
     * Get the source associated to this restore instance
     * @return Instance|null
     */
    public function getSourceInstance()
    {
        return $this->source;
    }

    /**
     * @param $manifest_file
     */
    public function setIniFilesToExclude($manifest)
    {

        $system_config_file_info = $this->readSystemIniConfigFileFromManifest($manifest);

        if ($this->getProcess() == self::CLONE_PROCESS) {
            // src
            if (isset($system_config_file_info['location']) && $system_config_file_info['location'] == 'local') {
                $this->iniFilesToExclude[] = $system_config_file_info['file'];
            }

            // remote
            $remoteSystemConfgFilePath = $this->app->getSystemIniConfigFilePath();
            if (!empty($remoteSystemConfgFilePath)) {
                $parts = explode('||', $remoteSystemConfgFilePath);
                if (isset($parts[0]) && isset($parts[1]) && $parts[1] == 'local') {
                    $this->iniFilesToExclude[] = $parts[0];
                }
            }
        }
    }

    /**
     * @param $manifest_file
     * @return array
     */
    public function readSystemIniConfigFileFromManifest($manifest_file)
    {
        $result = [];
        $access = $this->getAccess();
        $manifest = $access->fileGetContents($manifest_file);

        $manifest = explode(PHP_EOL, $manifest);
        $manifest = array_map('trim', $manifest);
        $manifest = array_filter($manifest, 'strlen');

        if (!empty($manifest)) {
            foreach ($manifest as $line) {
                $values = explode('    ', $line);
                $backupType = Backup::FULL_BACKUP;
                switch (count($values)) {
                    case 2:
                        $hash = '';
                        list($type, $path) = $values;
                        break;
                    case 3:
                        list($hash, $type, $path) = $values;
                        break;
                    case 4:
                        list($hash, $type, $path, $backupType) = $values;
                        break;
                }

                if ($type == 'conf_local' || $type == 'conf_external') {
                    $this->instance->system_config_file = $path;

                    $result = [
                        'location' => $type,
                        'file' => $hash,
                        'is_full' => $backupType == Backup::FULL_BACKUP,
                    ];

                    break;
                }
            }
        }

        return $result;
    }

    private function restoreFromVCS($src, $target)
    {
        $fileSystem = new Filesystem();
        $dest = implode(\DIRECTORY_SEPARATOR, [Environment::get('TEMP_FOLDER'),  md5(time()), $this->instance->name]);

        if ($fileSystem->exists($src . '/.svn')) {
            $className = Svn::class;
            $folder = '/.svn';
        } elseif ($fileSystem->exists($src . '/.git')) {
            $className = Git::class;
            $folder = '/.git';
        } else {
            return false;
        }

        $toCopy = $src . $folder;
        $dest .= $folder;
        $target .= $folder;
        $vcsInstance = new $className($this->instance);

        $fileSystem->mirror($toCopy, $dest);
        if ($this->restoreFolder($dest, $target, false)) {
            $vcsInstance->revert(dirname($target));
            return true;
        }
        return false;
    }

    public function applyChanges($changesFile)
    {
        $changes = file_exists($changesFile) ? file_get_contents($changesFile) : '';

        preg_match_all('/^D\s*(.*)/m', $changes, $matches);

        if (empty($matches[1])) {
            return;
        }

        foreach ($matches[1] as $file) {
            $this->access->deleteFile($file);
        }
    }

    public function setRestoreRoot($path): void
    {
        $this->restoreRoot = $path;
    }

    public function setRestoreDirname($dirName): void
    {
        $this->restoreDirname = $dirName;
    }

    protected function isSSHToLocal(): bool
    {
        $sourceInstance = $this->getSourceInstance();
        return $sourceInstance && $sourceInstance->type == 'ssh' && $this->instance->type == 'local';
    }

    protected function isLocalToSSH(): bool
    {
        $sourceInstance = $this->getSourceInstance();
        return $sourceInstance && $sourceInstance->type == 'local' && $this->instance->type == 'ssh';
    }

    public function removeRestoreFolder(): void
    {
        $this->removeFolder($this->getRestoreFolder());
    }

    public function removeRestoreRootFolder(): void
    {
        $this->removeFolder($this->getRestoreRoot());
    }

    private function removeFolder($path):void
    {
        $flags = '-Rf';
        if (ApplicationHelper::isWindows()) {
            $flags = "-r";
        }

        $this->access->shellExec(
            sprintf("rm %s %s", $flags, $path)
        );
    }
}
