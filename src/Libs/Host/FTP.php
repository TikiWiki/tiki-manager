<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Libs\Host;

class FTP
{
    private $host;
    private $port;
    private $user;
    private $pass;
    private $conn;

    public function __construct($host, $user, $pass, $port)
    {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->port = $port;
    }

    public function __destruct()
    {
        if ($this->conn) {
            ftp_close($this->conn);
        }
    }

    public function connect()
    {
        if ($this->conn) {
            return;
        }

        $conn = ftp_connect($this->host, $this->port, 15);
        if ($conn) {
            if (ftp_login($conn, $this->user, $this->pass)) {
                $this->conn = $conn;
                ftp_pasv($conn, true);
                return true;
            }
        }

        return false;
    }

    public function createDirectory($path)
    {
        $this->connect();
        // TODO MAKE IT RECURSIVE
        return ftp_mkdir($this->conn, $path);
    }

    public function listFiles($dir)
    {
        $this->connect();
        return ftp_nlist($this->conn, $dir);
    }

    public function fileExists($filename)
    {
        $this->connect();

        $dir = dirname($filename);
        $base = basename($filename);

        $list = ftp_nlist($this->conn, $dir);

        if (in_array($filename, $list) || in_array($base, $list)) {
            return true;
        } else {
            $list = ftp_nlist($this->conn, "-a $dir");

            return in_array($filename, $list) || in_array($base, $list);
        }
    }

    public function getContent($filename)
    {
        $this->connect();
        $fp = $this->getResource($filename);
        $content = '';

        if (!is_resource($fp)) {
            return $content;
        }

        while (!feof($fp)) {
            $content .= fread($fp, 8192);
        }
        fclose($fp);

        return $content;
    }

    public function getResource($filename, $type = FTP_ASCII)
    {
        $this->connect();
        $fp = tmpfile();
        if (ftp_fget($this->conn, $fp, $filename, $type)) {
            rewind($fp);
            return $fp;
        }
        return false;
    }

    public function sendFile($localFile, $remoteFile)
    {
        $this->connect();
        ftp_put($this->conn, $remoteFile, $localFile, FTP_BINARY);
        ftp_chmod($this->conn, 0644, $remoteFile);
    }

    public function chmod($level, $remoteFile)
    {
        $this->connect();
        ftp_chmod($this->conn, $level, $remoteFile);
    }

    public function receiveFile($remoteFile, $localFile)
    {
        $this->connect();
        ftp_get($this->conn, $localFile, $remoteFile, FTP_BINARY);
    }

    public function removeFile($remoteFile)
    {
        $this->connect();
        ftp_delete($this->conn, $remoteFile);
    }

    public function getPWD()
    {
        $this->connect();
        return ftp_pwd($this->conn);
    }

    public function rename($from, $to)
    {
        $this->connect();
        return ftp_rename($this->conn, $from, $to);
    }

    public function copy($from, $to)
    {
        $this->connect();
        $fp = $this->getResource($from);
        if (!is_resource($fp)) {
            return false;
        }
        $ret = ftp_fput($this->conn, $to, $fp);
        fclose($fp);
        return $ret;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
