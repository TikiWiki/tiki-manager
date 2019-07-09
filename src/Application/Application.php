<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @copyright (c) 2016, Avan.Tech, et. al.
 * @copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application;

use TikiManager\Libs\Database\Database;

abstract class Application
{
    protected $instance;

    public function __construct(Instance $instance)
    {
        $this->instance = $instance;
    }

    public static function getApplications(Instance $instance)
    {
        $objects = [];

        $dir = ROOT_PATH . '/Application';
        $files = scandir($dir);

        $apps = [];
        foreach ($files as $file) {
            if (preg_match('/^(\w+)\.php$/', $file, $matches)) {
                $apps[] = $matches[1];
            }
        }

        foreach ($apps as $className) {
            if (! class_exists($className) && is_subclass_of('TikiManager\Application\\'.$className, 'TikiManager\Application\Application')) {
                $className = 'TikiManager\Application\\'.$className;
                $objects[] = new $className($instance);
            }
        }

        return $objects;
    }

    abstract public function getName();

    abstract public function getVersions();

    abstract public function getCompatibleVersions();

    abstract public function isInstalled();

    abstract public function install(Version $version);

    abstract public function getInstallType();

    abstract public function getBranch();

    abstract public function getUpdateDate();

    abstract public function getSourceFile(Version $version, $filename);

    abstract public function performActualUpdate(Version $version);

    abstract public function performActualUpgrade(Version $version, $abort_on_conflict);

    abstract public function extractTo(Version $version, $folder);

    abstract public function getFileLocations();

    abstract public function requiresDatabase();

    abstract public function getAcceptableExtensions();

    abstract public function setupDatabase(Database $database);

    abstract public function restoreDatabase(Database $database, $remoteFile);

    abstract public function backupDatabase($targetFile);

    abstract public function removeTemporaryFiles();

    public function beforeChecksumCollect()
    {
    }

    /**
     * Perform an instance branch update/upgrade
     * @param Instance $instance
     * @param null $version
     * @param bool $checksumCheck
     * @return array
     */
    public function performUpdate(Instance $instance, $version = null, $checksumCheck = false)
    {
        $current = $instance->getLatestVersion();

        if (is_null($version)) {
            // Simple update, copy from current
            $new = $instance->createVersion();
            $new->type = $current->type;
            $new->branch = $current->branch;
            $new->date = date('Y-m-d');
            $new->save();
        } else {
            // Provided version, copy properties
            $new = $instance->createVersion();
            $new->type = $version->type;
            $new->branch = $version->branch;
            $new->date = $version->date;
            $new->save();
        }

        if ($checksumCheck) {
            info('Checking old instance checksums.');
            $oldPristine = $current->performCheck($instance);
            $oldPristine = $oldPristine['pri'] ?: [];

            info('Obtaining checksum from source.');
            $new->collectChecksumFromSource($instance);
        }

        $this->performActualUpdate($new);

        if (! $checksumCheck) {
            return [
                'new' => [],
                'mod' => [],
                'del' => [],
            ];
        }

        info('Checking new instance checksums.');
        $newDiff = $new->performCheck($instance);

        $toSave = [];
        foreach ($newDiff['new'] as $file => $hash) {
            if (isset($oldPristine[$file])) {
                $toSave[] = [$hash, $file];
                unset($newDiff['new'][$file]);
            }
        }
        $new->recordFiles($toSave);

        $toSave = [];
        foreach ($newDiff['mod'] as $file => $hash) {
            // If modified file was in the same state in previous version
            if (isset($oldPristine[$file])) {
                $toSave[] = [$hash, $file];
                unset($newDiff['mod'][$file]);
            }
        }
        $new->replaceFiles($toSave, $this);

        // Consider all missing files as conflicts
        $newDel = $newDiff['del'];

        return [
            'new' => $newDiff['new'],
            'mod' => $newDiff['mod'],
            'del' => $newDel,
        ];
    }

    /**
     * Perform instance upgrade to a higher branch version
     * @param Instance $instance
     * @param $version
     * @param bool $abort_on_conflict
     * @param bool $checksumCheck
     */
    public function performUpgrade(Instance $instance, $version, $abort_on_conflict = true, $checksumCheck = false)
    {
        $this->performActualUpgrade($version, $abort_on_conflict);

        // Create a new version if process did not abort
        $new = $instance->createVersion();
        $new->type = $version->type;
        $new->branch = $version->branch;
        $new->date = $version->date;
        $new->save();

        if ($checksumCheck) {
            info('Obtaining new checksum from source.');
            $new->collectChecksumFromSource($instance);
        }
    }

    public function registerCurrentInstallation()
    {
        if (! $this->isInstalled()) {
            return null;
        }

        $this->instance->app = $this->getName();
        $this->instance->save();

        $update = $this->instance->createVersion();
        $update->type = $this->getInstallType();
        $update->branch = $this->getBranch();
        $update->date = $this->getUpdateDate();
        $update->revision = $this->getRevision($this->instance->webroot);
        $update->save();

        return $update;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
