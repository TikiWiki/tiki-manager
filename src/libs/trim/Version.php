<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class Version
{
    const SQL_INSERT_VERSION = "
        INSERT OR REPLACE INTO
            version
            (version_id, instance_id, type, branch, revision, date)
        VALUES
            (:id, :instance, :type, :branch, :revision, :date)
        ;";

    private $id;
    private $instance;
    public $type;
    public $branch;
    public $revision;
    public $date;
    public $audit;

    public function __construct($instance = null)
    {
        $this->instance = $instance;
    }

    public static function buildFake($type, $branch)
    {
        $v = new self;
        $v->type = $type;
        $v->branch = $branch;
        $v->date = date('Y-m-d');

        return $v;
    }

    public function save()
    {
        $params = [
            ':id' => $this->id,
            ':instance' => $this->instance,
            ':type' => $this->type,
            ':branch' => $this->branch,
            ':revision' => $this->revision,
            ':date' => $this->date,
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
        if (preg_match('/((\d+)(\.\d+)?|trunk)/', $branch, $matches)) {
            $result = $matches[0];
            $result = is_numeric($result)
                ? floatval($result)
                : $result;
        }
        return $result;
    }

    public function hasChecksums()
    {
        return Audit_Checksum::hasChecksums($this->id);
    }

    public function performCheck(Instance $instance)
    {
        $access = $instance->getBestAccess('scripting');
        $app = $instance->getApplication();
        $app->beforeChecksumCollect();
        $folder = $instance->webroot;
        $result = $instance->type === 'local'
            ? (Audit_Checksum::checksumLocalFolder($folder))
            : (Audit_Checksum::checksumRemoteFolder($folder, $access));

        return Audit_Checksum::validate($this->id, $result);
    }

    public function collectChecksumFromSource(Instance $instance)
    {
        $app = $instance->getApplication();
        $result = Audit_Checksum::checksumSource($this, $app);

        // Update revision information (requires cache folder created and populated in checksumSource)
        $folder = cache_folder($app, $this);
        $this->revision = $app->getRevision($folder);
        $this->save();

        return Audit_Checksum::saveChecksums($this->id, $result);
    }

    public function collectChecksumFromInstance(Instance $instance)
    {
        // Update revision information
        $this->revision = $instance->getRevision();
        $this->save();

        $access = $instance->getBestAccess('scripting');
        $folder = $instance->webroot;
        $result = $instance->type === 'local'
            ? (Audit_Checksum::checksumLocalFolder($folder))
            : (Audit_Checksum::checksumRemoteFolder($folder, $access));

        return Audit_Checksum::saveChecksums($this->id, $result);
    }

    public function recordFile($hash, $filename)
    {
        return Audit_Checksum::addFile($this->id, $hash, $filename);
    }

    public function recordFiles($hashFiles = [])
    {
        return Audit_Checksum::addFiles($this->id, $hashFiles);
    }

    public function removeFile($filename)
    {
        return Audit_Checksum::removeFile($this->id, $filename);
    }

    public function replaceFile($hash, $filename, Application $app)
    {
        return Audit_Checksum::replaceFile($this->id, $hash, $filename);
    }

    public function replaceFiles($hashFiles = [], $app = null)
    {
        return Audit_Checksum::replaceFiles($this->id, $hashFiles);
    }

    public function getFileMap()
    {
        return Audit_Checksum::getChecksums($this->id);
    }

    private function saveHashDump($output, Application $app)
    {
        return Audit_Checksum::saveChecksums($this->id);
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
