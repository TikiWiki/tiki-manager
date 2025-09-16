<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @copyright (c) 2016, Avan.Tech, et. al.
 * @copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application;

use TikiManager\Config\App;
use TikiManager\Libs\Database\Database;
use TikiManager\Style\TikiManagerStyle;

abstract class Application
{
    /**
     * @var Instance
     */
    protected $instance;

    /** @var TikiManagerStyle $io */
    protected $io;

    public function __construct(Instance $instance)
    {
        $this->instance = $instance;
        $this->io = App::get('io');
    }

    public static function getApplications(Instance $instance)
    {
        $objects = [];

        $dir = __DIR__;
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

    abstract public function getUpgradableVersions(Version $currentVersion, bool $onlySupported);

    abstract public function isInstalled();

    abstract public function install(Version $version, $checksumCheck = false);

    abstract public function getInstallType($refresh = false);

    abstract public function getBranch($refresh = false);

    abstract public function getUpdateDate();

    abstract public function getSourceFile(Version $version, $filename);

    abstract public function performActualUpdate(Version $version, $options = []);

    abstract public function performActualUpgrade(Version $version, $options = []);

    abstract public function extractTo(Version $version, $folder);

    abstract public function getFileLocations();

    abstract public function requiresDatabase();

    abstract public function getAcceptableExtensions();

    abstract public function setupDatabase(Database $database);

    abstract public function deleteAllTables();

    abstract public function restoreDatabase(Database $database, string $remoteFile, bool $clone);

    abstract public function backupDatabase(string $targetFile, bool $indexMode);

    abstract public function removeTemporaryFiles();

    abstract public function setPref(string $prefName, string $prefValue): bool;

    abstract public function getPref(string $prefName);

    public function beforeChecksumCollect()
    {
    }

    /**
     * Perform an instance branch update/upgrade
     * @param Instance $instance
     * @param null $version
     * @param array $options
     * @return array
     */
    public function performUpdate(Instance $instance, $version = null, $options = [])
    {
        $current = $instance->getLatestVersion();

        if (is_null($version)) {
            // Simple update, copy from current
            $new = $instance->createVersion();
            $new->type = $current->type;
            $new->branch = $current->branch;
            $new->date = date('Y-m-d');
        } else {
            // Provided version, copy properties
            $new = $instance->createVersion();
            $new->type = $version->type;
            $new->branch = $version->branch;
            $new->date = $version->date;
        }
        $new->repo_url = $instance->repo_url;
        $new->action = 'update';

        $checksumCheck = isset($options['checksum-check']) && is_bool($options['checksum-check']) ?
            $options['checksum-check'] : false;

        if ($checksumCheck) {
            $this->io->writeln('Checking old instance checksums.');
            $oldPristine = $current->performCheck($instance);
            $oldPristine = $oldPristine['pri'] ?: [];

            $this->io->writeln('Obtaining checksum from source.');
            $new->collectChecksumFromSource($instance);
        }

        $this->performActualUpdate($new, $options);

        //Update new version with revision
        $new->revision = $this->getRevision();
        //Update date revision
        $new->date_revision = $this->getDateRevision();

        $new->save();

        if (! $checksumCheck) {
            return [
                'new' => [],
                'mod' => [],
                'del' => [],
            ];
        }

        $this->io->writeln('Checking new instance checksums.');
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
     * @param array $options
     */
    public function performUpgrade(Instance $instance, $version, $options = [])
    {
        $this->performActualUpgrade($version, $options);

        // Create a new version if process did not abort
        $new = $instance->createVersion();
        $new->type = $version->type;
        $new->branch = $version->branch;
        $new->date = $version->date;
        $new->revision = $instance->getRevision();
        $new->date_revision = $instance->getDateRevision();
        $new->repo_url = $instance->repo_url;
        $new->action = 'upgrade';
        $new->save();

        if (isset($options['checksum-check']) && $options['checksum-check']) {
            $this->io->writeln('Obtaining new checksum from source.');
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

        return $this->instance->updateVersion();
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
