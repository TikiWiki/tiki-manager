<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

abstract class Application
{
    protected $instance;

    function __construct(Instance $instance)
    {
        $this->instance = $instance;
    }

    public static function getApplications(Instance $instance)
    {
        $objects = array();

        $dir = dirname(__FILE__) . "/appinfo";
        $files = scandir($dir);

        $apps = array();
        foreach ($files as $file) {
            if (preg_match('/^(\w+)\.php$/', $file, $matches))
                $apps[] = $matches[1];
        }

        foreach ($apps as $name) {
            $classname = 'Application_' . ucfirst($name);
            if (! class_exists($classname))
                require "$dir/$name.php";

            $objects[] = new $classname($instance);
        }

        return $objects;
    }

    abstract function getName();

    abstract function getVersions();

    abstract function isInstalled();

    abstract function install(Version $version);

    abstract function getInstallType();

    abstract function getBranch();

    abstract function getUpdateDate();

    abstract function getSourceFile(Version $version, $filename);

    abstract function performActualUpdate(Version $version);

    abstract function performActualUpgrade(Version $version, $abort_on_conflict);

    abstract function extractTo(Version $version, $folder);

    abstract function getFileLocations();

    abstract function requiresDatabase();

    abstract function getAcceptableExtensions();

    abstract function setupDatabase(Database $database);

    abstract function restoreDatabase(Database $database, $remoteFile);

    abstract function backupDatabase($targetFile);

    abstract function removeTemporaryFiles();

    function beforeChecksumCollect()
    {
    }

    function performUpdate(Instance $instance, $version = null)
    {
        $current = $instance->getLatestVersion();

        if (is_null($version)) {
            // Simple update, copy from current
            $new = $instance->createVersion();
            $new->type = $current->type;
            $new->branch = $current->branch;
            $new->date = date('Y-m-d');
            $new->save();
        }
        else {
            // Provided version, copy properties
            $new = $instance->createVersion();
            $new->type = $version->type;
            $new->branch = $version->branch;
            $new->date = $version->date;
            $new->save();
        }
        info('Checking old instance checksums.');
        $oldPristine = $current->performCheck($instance);
        $oldPristine = $oldPristine['pri'] ?: array();

        info('Obtaining checksum from source.');
        $new->collectChecksumFromSource($instance);
        $this->performActualUpdate($new);

        info('Checking new instance checksums.');
        $newDiff = $new->performCheck($instance);

        $toSave = array();
        foreach ($newDiff['new'] as $file => $hash) {
            if (isset($oldPristine[$file])) {
                $toSave[] = array($hash, $file);
                unset($newDiff['new'][$file]);
            }
        }
        $new->recordFiles($toSave);

        $toSave = array();
        foreach ($newDiff['mod'] as $file => $hash) {
            // If modified file was in the same state in previous version
            if (isset($oldPristine[$file])) {
                $toSave[] = array($hash, $file);
                unset($newDiff['mod'][$file]);
            }
        }
        $new->replaceFiles($toSave, $this);

        // Consider all missing files as conflicts
        $newDel = $newDiff['del'];

        return array(
            'new' => $newDiff['new'],
            'mod' => $newDiff['mod'],
            'del' => $newDel,
        );
    }

    function performUpgrade(Instance $instance, $version, $abort_on_conflict = true)
    {
        $this->performActualUpgrade($version, $abort_on_conflict);
    }

    function registerCurrentInstallation()
    {
        if (! $this->isInstalled())
            return null;

        $this->instance->app = $this->getName();
        $this->instance->save();

        $update = $this->instance->createVersion();
        $update->type = $this->getInstallType();
        $update->branch = $this->getBranch();
        $update->date = $this->getUpdateDate();
        $update->save();

        return $update;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
