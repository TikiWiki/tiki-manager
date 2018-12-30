<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application;

use TikiManager\Application\Exception\RestoreErrorException;
use TikiManager\Libs\Helpers\ApplicationHelper;

class Restore extends Backup
{
    protected $restoreRoot;
    protected $restoreDirname;

    public function __construct($instance)
    {
        parent::__construct($instance);
        $this->restoreRoot = "{$instance->tempdir}/restore";
        $this->restoreDirname = "{$instance->id}-{$instance->name}";
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

        $content = trim($content, DIRECTORY_SEPARATOR);
        return $content;
    }

    public function getRestoreFolder()
    {
        return $this->getRestoreRoot() . '/' . $this->restoreDirname;
    }

    public function getRestoreRoot()
    {
        return rtrim($this->restoreRoot, '/');
    }

    public function prepareArchiveFolder($srcArchive)
    {
        $access = $this->access;
        $instance = $this->instance;
        $archivePath = $srcArchive;
        $archiveRoot = $this->getRestoreRoot();

        if ($instance->type !== 'local') {
            $archivePath = $this->uploadArchive($srcArchive);
        }

        $args = ['-p', $archiveRoot];
        $command = $access->createCommand('mkdir', $args);
        $command->run();

        if ($command->getReturn() !== 0) {
            throw new RestoreErrorException(
                "Can't create '$archiveRoot': "
                . $command->getStderrContent(),
                RestoreErrorException::CREATEDIR_ERROR
            );
        }

        $args = ['-jxp', '-C', $archiveRoot, '-f', $archivePath];
        $command = $access->createCommand('tar', $args);
        $command->run();

        if ($command->getReturn() !== 0) {
            throw new RestoreErrorException(
                "Can't extract '$archivePath' to '$archiveRoot': "
                . $command->getStderrContent(),
                RestoreErrorException::DECOMPRESS_ERROR
            );
        }

        $this->restoreDirname = $this->getFolderNameFromArchive($srcArchive);
        $archiveFolder = $archiveRoot . DIRECTORY_SEPARATOR . $this->restoreDirname;
        return $archiveFolder;
    }

    public function readManifest($manifestPath)
    {
        $access = $this->getAccess();
        $webroot = rtrim($this->instance->webroot, DIRECTORY_SEPARATOR);

        $archiveFolder = dirname($manifestPath);
        $manifest = $access->fileGetContents($manifestPath);
        $manifest = explode("\n", $manifest);
        $manifest = array_map('trim', $manifest);
        $manifest = array_filter($manifest, 'strlen');

        $folders = [];
        if (empty($manifest)) {
            throw new RestoreErrorException(
                "Manifest file is invalid: '{$manifestPath}'",
                RestoreErrorException::MANIFEST_ERROR
            );
        }

        foreach ($manifest as $line) {
            list($hash, $type, $destination) = explode('    ', $line, 3);

            $source = $archiveFolder
                . DIRECTORY_SEPARATOR
                . $hash
                . DIRECTORY_SEPARATOR
                . basename($destination);

            if ($destination{0} === DIRECTORY_SEPARATOR) {
                warning("manifest.txt shouldn't have absolute paths like '{$destination}'");
                if ($type === 'app') {
                    $destination = '';
                } else {
                    warning("Skipping '$destination', because I can't guess where to place it!");
                    continue;
                }
            }

            $destination = $webroot . DIRECTORY_SEPARATOR . $destination;
            $destination = ApplicationHelper::getAbsolutePath($destination);

            $folders[] = [
                $type,
                $source,
                $destination
            ];
        }
        return $folders;
    }

    public function restoreFiles($srcContent = null)
    {
        if (is_dir($srcContent)) {
            $this->restoreFilesFromFolder($srcContent);
        } elseif (is_file($srcContent)) {
            $this->restoreFilesFromArchive($srcContent);
        }
    }

    public function restoreFilesFromArchive($srcArchive)
    {
        $srcFolder = $this->prepareArchiveFolder($srcArchive);
        return $this->restoreFilesFromFolder($srcFolder);
    }

    public function restoreFilesFromFolder($srcFolder)
    {
        $instance = $this->instance;
        $manifest = "{$srcFolder}/manifest.txt";
        $folders = $this->readManifest($manifest);

        foreach ($folders as $key => $folder) {
            list($type, $src, $target) = $folder;
            $this->restoreFolder($src, $target);
        }
    }

    public function restoreFolder($src, $target)
    {
        $access = $this->getAccess();
        $instance = $this->instance;
        $src = rtrim($src, '/');
        $target = rtrim($target, '/');

        if (empty($src) || empty($target)) {
            throw new RestoreErrorException(
                "Invalid paths:\n \$src='$src';\n \$target='$target';",
                RestoreErrorException::INVALID_PATHS
            );
        }

        $command = $access->createCommand('mkdir', ['-p', $target]);
        $command->run();

        if ($command->getReturn() !== 0) {
            throw new RestoreErrorException(
                "Can't create target folder '$target': "
                . $command->getStderrContent(),
                RestoreErrorException::CREATEDIR_ERROR
            );
        }

        $command = $access->createCommand('rsync');
        $command->setArgs([
            '-a', '--delete',
            '--exclude', '/.htaccess',
            '--exclude', '/maintenance.php',
            '--exclude', '/db/local.php',
            $src . '/',
            $target . '/'
        ]);
        $command->run();

        if ($command->getReturn() !== 0) {
            throw new RestoreErrorException(
                "Failed copying '$src' to '$target': "
                . $command->getStderrContent(),
                RestoreErrorException::COPY_ERROR
            );
        }

        if ($access->fileExists($src . '/.htaccess')) {
            $command = $access->createCommand('rsync');
            $command->setArgs([
                $src . '/.htaccess',
                $target . '/.htaccess' . ($instance->isLocked() ? '.bak' : '')
            ]);
            $command->run();
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
}
