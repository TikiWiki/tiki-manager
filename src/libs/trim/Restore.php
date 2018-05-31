<?php

class Restore extends Backup
{
    public function getFolderNameFromArchive($srcArchive)
    {
        $bf = bzopen($srcArchive, 'r');
        $char = bzread($bf, 1);
        $content = $char;

        while (!feof($bf)) {
            $char = bzread($bf, 1);
            if($char === "\0") {
                break;
            }
            $content .= $char;
        }
        bzclose($bf);

        $content = trim($content, DIRECTORY_SEPARATOR);
        return $content;
    }

    public function prepareArchiveFolder($srcArchive)
    {
        $access = $this->access;
        $instance = $this->instance;
        $archivePath = $srcArchive;
        $archiveFolder = "{$instance->tempdir}/restore";

        if($instance->type !== 'local') {
            $archivePath = $access->uploadArchive($srcArchive);
        }

        $args = array('-p', $archiveFolder);
        $command = $access->createCommand('mkdir', $args);
        $command->run();

        if($command->getReturn() !== 0) {
            throw new RestoreError(
                "Can't create '$archiveFolder': "
                . $command->getStderrContent(),
                RestoreError::CREATEDIR_ERROR
            );
        }

        $args = array('-jxp', '-C', $archiveFolder, '-f', $archivePath);
        $command = $access->createCommand('tar', $args);
        $command->run();

        if($command->getReturn() !== 0) {
            throw new RestoreError(
                "Can't extract '$archivePath' to '$archiveFolder': "
                . $command->getStderrContent(),
                RestoreError::DECOMPRESS_ERROR
            );
        }

        $archiveApp = $archiveFolder 
            . DIRECTORY_SEPARATOR
            . $this->getFolderNameFromArchive($srcArchive);
        return $archiveApp;
    }

    public function readManifest($manifestPath)
    {
        $access = $this->getAccess();
        $webroot = rtrim($this->instance->webroot, DIRECTORY_SEPARATOR);

        $manifestDir = dirname($manifestPath);
        $manifest = $access->fileGetContents($manifestPath);
        $manifest = explode("\n", $manifest);
        $manifest = array_map('trim', $manifest);
        $manifest = array_filter($manifest, 'strlen');

        $folders = array();
        if (empty($manifest)) {
            throw new RestoreError(
                "Can't extract '$archivePath' to '$archiveFolder': "
                . $command->getStderrContent(),
                RestoreError::MANIFEST_ERROR
            );
        }

        foreach ($manifest as $line) {
            list($hash, $type, $destination) = explode('    ', $line, 3);

            $source = $manifestDir 
                . DIRECTORY_SEPARATOR 
                . $hash
                . DIRECTORY_SEPARATOR 
                . basename($destination);

            if($destination{0} === DIRECTORY_SEPARATOR) {
                warning("manifest.txt shouldn't have absolute paths like '{$destination}'");
                if ($type === 'app') {
                    $destination = '';
                } else {
                    warning("Skipping '$destination', because I can't guess where to place it!");
                    continue;
                }
            }

            $destination = $webroot . DIRECTORY_SEPARATOR . $destination;
            $destination = get_absolute_path($destination);

            $folders[] = array(
                $type,
                $source,
                $destination
            );
        }
        return $folders;
    }

    public function restoreFiles($srcContent=null)
    {
        if (is_dir($srcContent)) {
            $this->restoreFilesFromFolder($srcContent);
        }
        else if(is_file($srcContent)) {
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

        if(empty($src) || empty($target)) {
            throw new RestoreError(
                "Invalid paths:\n \$src='$src';\n \$target='$target';",
                RestoreError::INVALID_PATHS
            );
        }

        $command = $access->createCommand('mkdir', array('-p', $target));
        $command->run();

        if ($command->getReturn() !== 0) {
            throw new RestoreError(
                "Can't create target folder '$target': "
                . $command->getStderrContent(),
                RestoreError::CREATEDIR_ERROR
            );
        }

        $command = $access->createCommand('rsync');
        $command->setArgs(array(
            '-a', '--delete',
            '--exclude', '/.htaccess',
            '--exclude', '/maintenance.php',
            '--exclude', '/db/local.php',
            $src . '/', 
            $target . '/'
        ));
        $command->run();

        if ($command->getReturn() !== 0) {
            throw new RestoreError(
                "Failed copying '$src' to '$target': "
                . $command->getStderrContent(),
                RestoreError::COPY_ERROR
            );
        }

        if($access->fileExists($src . '/.htaccess')) {
            $command = $access->createCommand('rsync');
            $command->setArgs(array(
                $src . '/.htaccess',
                $target . '/.htaccess' . ($instance->isLocked() ? '.bak' : '')
            ));
            $command->run();
        }

        return true;
    }

    public function uploadArchive($srcArchive)
    {
        $access = $this->access;
        $instance = $this->instance;

        $basename = basename($srcArchive);
        $remote = $this->getWorkPath($basename);
        $access->uploadFile($srcArchive, $remote);
        return $remote;
    }

}

class RestoreError extends Exception
{
    const CREATEDIR_ERROR = 1;
    const DECOMPRESS_ERROR = 2;
    const MANIFEST_ERROR = 3;
    const COPY_ERROR = 4;
    const INVALID_PATHS = 5;
}
