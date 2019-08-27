<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Access;

use TikiManager\Libs\Host\FTP as FTPHost;
use TikiManager\Application\Instance;

class FTP extends Access implements Mountable
{
    private $lastMount;

    public function __construct(Instance $instance)
    {
        parent::__construct($instance, 'ftp');
        $this->port = 21;
    }

    // TODO: change directory using FTP
    public function openShell($workingDir = '')
    {
        echo "User: {$this->user}, Pass: {$this->password}\n";
        passthru("ftp {$this->host} {$this->port}");
    }

    public function getHost()
    {
        return new FTPHost($this->host, $this->user, $this->password, $this->port);
    }

    public function firstConnect()
    {
        $conn = $this->getHost();

        return $conn->connect();
    }

    public function getInterpreterPath($instance2 = null)
    {
        if ($instance2 instanceof Instance) {
            $this->instance = $instance2;
        }

        $result = $this->runPHP(
            dirname(__FILE__) . '/../../scripts/checkversion.php',
            [$this->instance->webroot]
        );

        if (preg_match('/^[5-9]\./', $result)) {
            return 'mod_php';
        }
    }

    public function getSVNPath()
    {
        return 1;
    }

    public function getInterpreterVersion($interpreter)
    {
        return 99999;
    }

    public function getDistributionName($interpreter)
    {
        return 'Unknown';
    }

    public function fileExists($filename)
    {
        if ($filename{0} != '/') {
            $filename = $this->instance->getWebPath($filename);
        }

        $ftp = $this->getHost();
        return $ftp->fileExists($filename);
    }

    public function fileGetContents($filename)
    {
        $ftp = $this->getHost();
        return $ftp->getContent($filename);
    }

    public function fileModificationDate($filename)
    {
    }

    public function runPHP($localFile, $args = [])
    {
        foreach ($args as & $potentialPath) {
            if ($potentialPath{0} == '/') {
                $potentialPath = $this->obtainRelativePathTo(
                    $potentialPath,
                    $this->instance->webroot
                );
            }
        }
        $host = $this->getHost();

        $remoteName = 'trim_' . md5($localFile) . '.php';
        $remoteFile = $this->instance->getWebPath($remoteName);

        array_unshift($args, null);
        $arg = http_build_query($args, '', '&');

        $host->sendFile($localFile, $remoteFile);
        $output = file_get_contents($this->instance->getWebUrl($remoteName) . '?' . $arg);

        $host->removeFile($remoteFile);

        return $output;
    }

    public function downloadFile($filename)
    {
        if ($filename{0} != '/') {
            $filename = $this->instance->getWebPath($filename);
        }

        $dot = strrpos($filename, '.');
        $ext = substr($filename, $dot);

        $local = tempnam($_ENV['TEMP_FOLDER'], 'trim');

        $host = $this->getHost();
        $host->receiveFile($filename, $local);

        rename($local, $local . $ext);
        chmod($local . $ext, 0644);

        return $local . $ext;
    }

    public function uploadFile($filename, $remoteLocation)
    {
        $host = $this->getHost();
        if ($remoteLocation{0} == '/') {
            $host->sendFile($filename, $remoteLocation);
        } else {
            $host->sendFile($filename, $this->instance->getWebPath($remoteLocation));
        }
    }

    public function moveFile($remoteSource, $remoteTarget)
    {
        if ($remoteSource{0} != '/') {
            $remoteSource = $this->instance->getWebPath($remoteSource);
        }
        if ($remoteTarget{0} != '/') {
            $remoteTarget = $this->instance->getWebPath($remoteTarget);
        }

        $host = $this->getHost();
        $host->rename($remoteSource, $remoteTarget);
    }

    public function copyFile($remoteSource, $remoteTarget)
    {
        if ($remoteSource{0} != '/') {
            $remoteSource = $this->instance->getWebPath($remoteSource);
        }
        if ($remoteTarget{0} != '/') {
            $remoteTarget = $this->instance->getWebPath($remoteTarget);
        }

        $host = $this->getHost();
        $host->copy($remoteSource, $remoteTarget);
    }

    public function deleteFile($filename)
    {
        if ($filename{0} != '/') {
            $filename = $this->instance->getWebPath($filename);
        }

        $host = $this->getHost();
        $host->removeFile($filename);
    }

    public function localizeFolder($remoteLocation, $localMirror)
    {
        if ($remoteLocation{0} != '/') {
            $remoteLocation = $this->instance->getWebPath($remoteLocation);
        }

        $compress = in_array('zlib', $this->instance->getExtensions());

        $name = md5(time()) . '.tar';
        if ($compress) {
            $name .= '.gz';
        }

        $remoteTar = $this->instance->getWebPath($name);
        $this->runPHP(
            dirname(__FILE__) . '/../../scripts/package_tar.php',
            [$remoteTar, $remoteLocation]
        );

        $localized = $this->downloadFile($remoteTar);
        $this->deleteFile($remoteTar);

        $current = getcwd();
        if (! file_exists($localMirror)) {
            mkdir($localMirror);
        }

        chdir($localMirror);

        $eLoc = escapeshellarg($localized);
        if ($compress) {
            passthru("tar -zxf $eLoc");
        } else {
            `tar -xf $eLoc`;
        }

        chdir($current);
    }

    public static function obtainRelativePathTo($targetFolder, $originFolder)
    {
        $parts = [];
        while ((0 !== strpos($targetFolder, $originFolder))
            && $originFolder != '/' && $originFolder != '') {
            $originFolder = dirname($originFolder);
            $parts[] = '..';
        }

        $out = null;
        if (strpos($targetFolder, $originFolder) === false) {
            // Target is under the origin
            $relative = substr($targetFolder, strlen($originFolder));
            $out = ltrim(implode('/', $parts) . '/' . ltrim($relative, '/'), '/');
        }

        if (empty($out)) {
            $out = '.';
        }

        return $out;
    }

    public function mount($target)
    {
        if ($this->lastMount) {
            return false;
        }

        $ftp = $this->getHost();
        $pwd = $ftp->getPWD();
        $toRoot = preg_replace('/\w+/', '..', $pwd);

        $this->lastMount = $target;

        $remote = escapeshellarg("ftp://{$this->user}:{$this->password}@{$this->host}$toRoot");
        $local = escapeshellarg($target);

        $cmd = "curlftpfs $remote $local";
        shell_exec($cmd);

        return true;
    }

    public function umount()
    {
        if ($this->lastMount) {
            $loc = escapeshellarg($this->lastMount);
            `sudo umount $loc`;
            $this->lastMount = null;
        }
    }

    public function synchronize($source, $mirror, $keepFolderName = false)
    {
        $source = rtrim($source, '/') . ($keepFolderName ? '' : '/');
        $mirror = rtrim($mirror, '/') . '/';

        $source = escapeshellarg($source);
        $target = escapeshellarg($mirror);
        $tmp = escapeshellarg($_ENV['RSYNC_FOLDER']);
        $cmd = 'rsync -rDu --no-p --no-g --size-only ' .
            '--exclude .svn --exclude copyright.txt --exclude changelog.txt ' .
            "--temp-dir=$tmp $source $target";
        passthru($cmd);
    }

    public function copyLocalFolder($localFolder, $remoteFolder = '')
    {
        if ($remoteFolder{0} != '/') {
            $remoteFolder = $this->instance->getWebPath($remoteFolder);
        }

        $compress = in_array('zlib', $this->instance->getExtensions());

        $current = getcwd();
        chdir($localFolder);

        $temp = $_ENV['TEMP_FOLDER'];
        $name = md5(time()) . '.tar';
        `chmod 777 db`;
        `tar --exclude=.svn -cf $temp/$name *`;
        if ($compress) {
            `gzip -5 $temp/$name`;
            $name .= '.gz';
        }

        chdir($current);

        $this->uploadFile("$temp/$name", $name);
        unlink("$temp/$name");

        $this->runPHP(
            dirname(__FILE__) . '/../../scripts/extract_tar.php',
            [$name, $remoteFolder]
        );

        $this->deleteFile($name);
    }
}
