<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application;

use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Application\Exception\RestoreErrorException;
use TikiManager\Application\Exception\VcsException;
use TikiManager\Application\Tiki\Handler\SystemConfigurationFile;
use TikiManager\Config\Environment;
use TikiManager\Libs\Helpers\ApplicationHelper;
use TikiManager\Libs\VersionControl\Git;

class Restore extends Backup
{
    const CLONE_PROCESS = 'clone';
    const RESTORE_PROCESS = 'restore';

    protected $restoreRoot;
    protected $restoreDirname;
    protected $restoreLockFile;
    protected $process;
    protected $source;
    protected $onlyData;
    protected $skipSystemConfigurationCheck = false;
    protected $allowCommonParents = 0;
    public $iniFilesToExclude = [];

    /**
     * Restore constructor.
     * @param Instance $instance
     * @param bool $direct
     * @param bool $onlyData
     * @param bool $skipSystemConfigurationCheck If should skip checking for dangerous directives in the system configuration file
     *
     * @throws Exception\FolderPermissionException
     */
    public function __construct(Instance $instance, bool $direct = false, bool $onlyData = false, bool $skipSystemConfigurationCheck = false, $allowCommonParents = 0, $excludeList = [])
    {
        parent::__construct($instance, $direct, true, false, $excludeList);

        $this->onlyData = $onlyData;
        $this->skipSystemConfigurationCheck = $skipSystemConfigurationCheck;
        $this->allowCommonParents = $allowCommonParents;

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
    public function prepareArchiveFolder($srcArchive)
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
        $differentInstance = $this->instance->getId() != $this->getSourceInstance()->getId();

        $skipPathCheck = ($this->allowCommonParents < 0) || (count(explode('/', $this->instance->webroot)) < $this->allowCommonParents);

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

        $windowsAbsolutePathsRegex = '/^([a-zA-Z]\:[\/,\\\\]).{1,}/';

        $folders = [];
        if (empty($manifest)) {
            throw new RestoreErrorException(
                "Manifest file is invalid: '{$manifestPath}'",
                RestoreErrorException::MANIFEST_ERROR
            );
        }

        foreach ($manifest as $line) {
            $backupType = Backup::FULL_BACKUP;
            $hash = '';

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

            if ($this->onlyData && $type !== 'data') {
                continue;
            }

            if ($this->direct) {
                $source = ($type === 'app') ? $destination : $this->getSourceInstance()->getWebPath($destination);
            } else {
                $source = $archiveFolder . DIRECTORY_SEPARATOR . $hash;
                $source .= $type != 'conf_external' ? DIRECTORY_SEPARATOR . basename($destination) : '';
            }

            $windowsAbsolutePaths = (preg_match($windowsAbsolutePathsRegex, $destination, $matches)) ? true : false;

            if ($destination[0] === '/' || $windowsAbsolutePaths) {
                if ($type === 'app') {
                    $destination = $webroot; // Always restore the main app to the instance root folder
                } elseif (! $skipPathCheck) {
                    $sourceInstanceParentFolder = $this->getSourceInstance()->webroot;
                    $targetInstanceParentFolder = $webroot;

                    // support restore if the absolute path of (e.g. conf_external) shares the Nth (N>=0) parent folder of the source webroot.
                    if ($this->allowCommonParents > 0) {
                        $sourceInstanceParentFolder = dirname($sourceInstanceParentFolder, $this->allowCommonParents);
                        $targetInstanceParentFolder = dirname($targetInstanceParentFolder, $this->allowCommonParents);
                    }

                    if (strncmp($sourceInstanceParentFolder, $destination, strlen($sourceInstanceParentFolder)) === 0) {
                        // relativize the path comparing with destination (or the Nth parent folder) instance in this case
                        $destination = $targetInstanceParentFolder . DIRECTORY_SEPARATOR . substr($destination, strlen($sourceInstanceParentFolder));
                    } else {
                        $this->io->warning("Skipping {$destination}. Path shouldn't have absolute paths, to avoid override data. You can use --allow-common-parent-levels if you want to allow restore from sibling folders");
                        continue;
                    }
                }
            } else {
                // make path and absolute path, based on the target instance
                $destination = $webroot . DIRECTORY_SEPARATOR . $destination;
            }

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
            if (substr($type, 0, 5) === 'conf_') {
                if (($this->instance->getId() != $this->getSourceInstance()->getId()) // restoring to a different instance
                    && (! $this->skipSystemConfigurationCheck) // and we should check for dangerous directives
                ) {
                    $configFileToTest = $src;

                    $cleanConfigFileCopyAfter = false;
                    if ($this->isDirectSSHToLocal()) { // file is not accessible locally, so we need a copy of the file for inspection
                        $configFileToTest = $this->instance->tempdir
                            . DIRECTORY_SEPARATOR
                            . 'tmp-sys-config-file-' . date("Ymd_H:i:s") . '-rand' . rand(0, 999999);
                        $this->getSourceInstance()->getBestAccess('filetransfer')->downloadFile($src, $configFileToTest);
                        $cleanConfigFileCopyAfter = true;
                    }

                    $systemConfigFileHandler = new SystemConfigurationFile();
                    if ($systemConfigFileHandler->hasDangerousDirectives($configFileToTest)) {
                        $info = pathinfo($target);
                        if ($info['extension'] == 'php' && (substr($info['filename'], -4) == '.ini')) { // extension is .ini.php
                            $info['extension'] = 'ini.php';
                        }

                        $target = substr($target, 0, strlen($target)-strlen($info['extension'])-1)
                            . '-cloned-' . date("Ymd_H:i:s") . '-rand' . rand(0, 999999)
                            . '.' . $info['extension'];
                    }

                    if ($cleanConfigFileCopyAfter) {
                        @unlink($configFileToTest);
                    }
                }

                if ($this->isDirectSSHToLocal()) {
                    $this->getSourceInstance()->getBestAccess()->downloadFile($src, $target);
                } else {
                    $this->getAccess()->uploadFile($src, $target);
                }

                continue; // end of conf file handling
            }

            if ($type == 'app' && !$isFull) {
                $this->restoreFromVCS($src, $target);
            }

            $this->restoreFolder($src, $target, $isFull);
        }

        if (! $this->onlyData) {
            $changes = "{$srcFolder}/changes.txt";
            $this->applyChanges($changes);
        }
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

            if (!empty($this->excludeList)) {
                foreach ($this->excludeList as $exclude) {
                    $rsyncExcludes[] = '--exclude';
                    $rsyncExcludes[] = $exclude['exclude'];
                }

                // Sync options for the temp folder
                $rsyncExcludes = array_merge($rsyncExcludes, [
                    '--include=temp/**',
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

            if ($localToSSH = $this->isDirectLocalToSSH()) {
                $sshPort = $access->port;
                $target = $access->getRsyncPrefix() . $target;
                $access = $this->source->getBestAccess();
            }

            if ($sshToLocal = $this->isDirectSSHToLocal()) {
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

            $adjustedRsyncCommand = $access->executeWithPriorityParams('rsync');
            $command = $access->createCommand($adjustedRsyncCommand);
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
                $htaccessTransferCommand = $access->executeWithPriorityParams('rsync');
                $command = $access->createCommand($htaccessTransferCommand);
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
            $adjustedBzipCmd = $access->executeWithPriorityParams('bzip2');
            $command = $access->createCommand($adjustedBzipCmd, $args);
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
        $adjustedTarCmd = $access->executeWithPriorityParams('tar');
        $command = $access->createCommand($adjustedTarCmd, $args);
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
     * Builds a list of INI files that are within the root folder, they should be excluded from the main code restore.
     * INI files have their own handling, so this list will be used to avoid restoring them with the main folder restore.
     *
     * @param $manifest
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

        if ($fileSystem->exists($src . '/.git')) {
            $className = Git::class;
            $folder = '/.git';
        } else {
            throw new VcsException("Unsupported VCS type");
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

    protected function isDirectSSHToLocal(): bool
    {
        if (! $this->direct) {
            return false;
        }

        $sourceInstance = $this->getSourceInstance();
        return $sourceInstance && $sourceInstance->type == 'ssh' && $this->instance->type == 'local';
    }

    protected function isDirectLocalToSSH(): bool
    {
        if (! $this->direct) {
            return false;
        }

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

        $cmd = sprintf("rm %s %s", $flags, $path);
        $cmd = $this->access->executeWithPriorityParams($cmd);
        $this->access->shellExec($cmd);
    }
}
