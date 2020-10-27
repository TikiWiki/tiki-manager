<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Access;

use TikiManager\Config\App;
use TikiManager\Application\Instance;

abstract class Access
{
    private $rowid;
    private $type;
    protected $instance;
    protected $io;

    public $host;
    public $user;
    public $password;
    public $port;

    const SQL_SELECT_ACCESS = <<<SQL
SELECT
    rowid, type, host, user, pass password
FROM
    access
WHERE
    instance_id = :id
;
SQL;

    const SQL_INSERT_ACCESS = <<<SQL
INSERT OR REPLACE INTO
    access
(instance_id, rowid, type, user, host, pass)
    VALUES
(:instance, :rowid, :type, :user, (:host || ':' || :port), :pass)
;
SQL;

    public function __construct(Instance $instance, $type)
    {
        $this->instance = $instance;
        $this->type = $type;
        $this->io = App::get('io');
    }

    public static function getClassFor($type)
    {
        $types = [
            'ftp'        => 'TikiManager\Access\FTP',
            'local'      => 'TikiManager\Access\Local',
            'ssh::nokey' => 'TikiManager\Access\SSH',
            'ssh'        => 'TikiManager\Access\SSH',
        ];

        if (!empty($types[$type])) {
            return $types[$type];
        }

        throw new \Exception("Unknown type: $type", 1);
    }

    public static function getAccessFor(Instance $instance)
    {
        $result = query(self::SQL_SELECT_ACCESS, [':id' => $instance->id]);

        $access = [];
        while ($row = $result->fetch()) {
            $class = self::getClassFor($row['type']);

            $a = new $class($instance);

            if ($row['type'] != 'local') {
                list($a->host, $a->port) = explode(':', $row['host']);
                $a->user = $row['user'];
                $a->password = $row['password'];
            } else {
                $a->user = $row['user'];
            }

            $access[] = $a;
        }

        if (empty($access)) { // Instance is not yet saved in database
            $class = self::getClassFor($instance->type);
            $a = new $class($instance);
            $access[] = $a;
        }

        return $access;
    }

    public function save()
    {
        $params = [
            ':instance' => $this->instance->id,
            ':rowid' => $this->rowid,
            ':type' => $this->type,
            ':host' => $this->host,
            ':user' => $this->user,
            ':pass' => $this->password,
            ':port' => $this->port,
        ];

        query(self::SQL_INSERT_ACCESS, $params);

        $rowid = rowid();
        if (! $this->rowid && $rowid) {
            $this->rowid = $rowid;
        }
    }

    public function changeType($type)
    {
        if (strpos($type, "{$this->type}::") === false) {
            $this->type = $type;
            return true;
        } else {
            return false;
        }
    }

    abstract public function firstConnect();

    abstract public function getInterpreterPath($instance2 = null);

    abstract public function createDirectory($path);

    abstract public function fileExists($filename);

    abstract public function fileGetContents($filename);

    abstract public function fileModificationDate($filename);

    abstract public function runPHP($localFile, $args = []);

    abstract public function downloadFile($filename);

    abstract public function uploadFile($filename, $remoteLocation);

    abstract public function deleteFile($filename);

    abstract public function moveFile($remoteSource, $remoteTarget);

    abstract public function copyFile($remoteSource, $remoteTarget);

    abstract public function localizeFolder($remoteLocation, $localMirror);
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
