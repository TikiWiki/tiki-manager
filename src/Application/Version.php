<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application;

use TikiManager\Libs\Audit\Checksum;

class Version
{
    const SQL_INSERT_VERSION = <<<SQL
        INSERT OR REPLACE INTO
            version
            (version_id, instance_id, type, branch, revision, date, action)
        VALUES
            (:id, :instance, :type, :branch, :revision, :date, :action)
        ;
SQL;

    private $id;
    private $instance;
    public $type;
    public $branch;
    public $revision;
    public $date;
    public $action;

    public function __construct($instance = null)
    {
        $this->instance = $instance;
    }

    public static function buildFake($type, $branch)
    {
        $v = new self;
        $v->type = strtolower($type);
        $v->branch = $branch;
        $v->date = date('Y-m-d');

        return $v;
    }

    /**
     * Save current Version instance
     * @return void
     */
    public function save()
    {
        $params = [
            ':id' => $this->id,
            ':instance' => $this->instance,
            ':type' => strtolower($this->type),
            ':branch' => $this->branch,
            ':revision' => $this->revision,
            ':date' => $this->date,
            ':action' => $this->action ?: 'create',
        ];

        query(self::SQL_INSERT_VERSION, $params);

        $rowid = rowid();

        if (! $this->id && $rowid) {
            $this->id = $rowid;
        }
    }

    public function getInstance()
    {
        return Instance::getInstance($this->instance);
    }

    public function getRevision()
    {
        return $this->revision;
    }

    public function getBranch()
    {
        return $this->branch;
    }

    public function getBaseVersion()
    {
        $branch = $this->getBranch();
        $result = null;
        if (preg_match('/((\d+)(\.\d+)?|trunk|master)/', $branch, $matches)) {
            $result = $matches[0];
            $result = is_numeric($result)
                ? floatval($result)
                : $result;
        }
        return $result;
    }

    public function hasChecksums()
    {
        return Checksum::hasChecksums($this->id);
    }

    public function performCheck(Instance $instance)
    {
        $access = $instance->getBestAccess('scripting');
        $app = $instance->getApplication();
        $app->beforeChecksumCollect();
        $folder = $instance->webroot;
        $result = $instance->type === 'local'
            ? (Checksum::checksumLocalFolder($folder))
            : (Checksum::checksumRemoteFolder($folder, $access));

        return Checksum::validate($this->id, $result);
    }

    public function collectChecksumFromSource(Instance $instance)
    {
        $app = $instance->getApplication();
        $result = Checksum::checksumSource($this, $app);

        // Update revision information (requires cache folder created and populated in checksumSource)
        $folder = cache_folder($app, $this);
        $this->revision = $app->getRevision($folder);
        $this->save();

        return Checksum::saveChecksums($this->id, $result);
    }

    public function collectChecksumFromInstance(Instance $instance)
    {
        // Update revision information
        $this->revision = $instance->getRevision();
        $this->save();

        $access = $instance->getBestAccess('scripting');
        $folder = $instance->webroot;
        $result = $instance->type === 'local'
            ? (Checksum::checksumLocalFolder($folder))
            : (Checksum::checksumRemoteFolder($folder, $access));

        return Checksum::saveChecksums($this->id, $result);
    }

    public function recordFile($hash, $filename)
    {
        return Checksum::addFile($this->id, $hash, $filename);
    }

    public function recordFiles($hashFiles = [])
    {
        return Checksum::addFiles($this->id, $hashFiles);
    }

    public function removeFile($filename)
    {
        return Checksum::removeFile($this->id, $filename);
    }

    public function replaceFile($hash, $filename, Application $app)
    {
        return Checksum::replaceFile($this->id, $hash, $filename);
    }

    public function replaceFiles($hashFiles = [], $app = null)
    {
        return Checksum::replaceFiles($this->id, $hashFiles);
    }

    public function getFileMap()
    {
        return Checksum::getChecksums($this->id);
    }

    private function saveHashDump($output, Application $app)
    {
        return Checksum::saveChecksums($this->id);
    }

    public function __toString()
    {
        return $this->type . ' : ' . $this->branch;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
