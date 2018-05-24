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
            (version_id, instance_id, type, branch, date)
        VALUES
            (:id, :instance, :type, :branch, :date)
        ;";

    private $id;
    private $instance;
    public $type;
    public $branch;
    public $date;
    public $audit;

    function __construct($instance=null)
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

    function save()
    {
        $params = array(
            ':id' => $this->id,
            ':instance' => $this->instance,
            ':type' => $this->type,
            ':branch' => $this->branch,
            ':date' => $this->date,
        );

        query(self::SQL_INSERT_VERSION, $params);

        $rowid = rowid();

        if (! $this->id && $rowid){
            $this->id = $rowid;
        }
    }

    function getInstance()
    {
        return Instance::getInstance($this->instance);
    }

    function getBranch() {
        return $this->branch;
    }

    function getBaseVersion() {
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

    function hasChecksums()
    {
        return Audit_Checksum::hasChecksums($this->id);
    }

    function performCheck(Instance $instance)
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

    function collectChecksumFromSource(Instance $instance)
    {
        $app = $instance->getApplication();
        $result = Audit_Checksum::checksumSource($this, $app);
        return Audit_Checksum::saveChecksums($this->id, $result);
    }

    function collectChecksumFromInstance(Instance $instance)
    {
        $access = $instance->getBestAccess('scripting');
        $folder = $instance->webroot;
        $result = $instance->type === 'local'
            ? (Audit_Checksum::checksumLocalFolder($folder))
            : (Audit_Checksum::checksumRemoteFolder($folder, $access));

        return Audit_Checksum::saveChecksums($this->id, $result);
    }

    function recordFile($hash, $filename)
    {
        return Audit_Checksum::addFile($this->id, $hash, $filename);
    }

    function recordFiles($hashFiles=array())
    {
        return Audit_Checksum::addFiles($this->id, $hashFiles);
    }

    function removeFile($filename)
    {
        return Audit_Checksum::removeFile($this->id, $filename);
    }

    function replaceFile($hash, $filename, Application $app)
    {
        return Audit_Checksum::replaceFile($this->id, $hash, $filename);
    }

    function replaceFiles($hashFiles=array(), $app=null)
    {
        return Audit_Checksum::replaceFiles($this->id, $hashFiles);
    }

    function getFileMap()
    {
        return Audit_Checksum::getChecksums($this->id);
    }

    private function saveHashDump($output, Application $app)
    {
        return Audit_Checksum::saveChecksums($this->id);
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
