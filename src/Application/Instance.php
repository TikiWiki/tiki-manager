<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @copyright (c) 2016, Avan.Tech, et. al.
 * @copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application;

use Exception;
use Exception\FolderPermissionException;
use Exception\RestoreErrorException;
use TikiManager\Application\Instance\CrontabManager;
use TikiManager\Application\Tiki\Versions\TikiRequirements;
use TikiManager\Config\App;
use TikiManager\Access\Access;
use TikiManager\Libs\Helpers\Archive;
use TikiManager\Libs\Database\Database;
use TikiManager\Libs\Host\Command;
use TikiManager\Libs\VersionControl\Svn;
use TikiManager\Libs\VersionControl\VersionControlSystem;

class Instance
{
    const TYPES = 'local,ftp,ssh';

    const SQL_SELECT_INSTANCE = <<<SQL
SELECT
    i.instance_id id, i.name, i.contact, i.webroot, i.weburl, i.tempdir, i.phpexec, i.app, a.type, v.branch, v.revision, v.type as vcs_type, v.action as last_action, i.state, v.date as last_action_date, v.date_revision as last_revision_date
FROM
    instance i
INNER JOIN access a
    ON i.instance_id=a.instance_id
LEFT JOIN
    version v ON i.instance_id = v.instance_id
;
SQL;

    const SQL_SELECT_INSTANCE_BY_ID = <<<SQL
SELECT
    i.instance_id id, i.name, i.contact, i.webroot, i.weburl, i.tempdir, i.phpexec, i.app, a.type, v.branch, v.revision, v.type as vcs_type, v.action as last_action, i.state, v.date as last_action_date, v.date_revision as last_revision_date
FROM
    instance i
INNER JOIN access a
    ON i.instance_id=a.instance_id
LEFT JOIN
    version v ON i.instance_id = v.instance_id
WHERE
    i.instance_id = :id
ORDER BY v.version_id DESC
;
SQL;

    const SQL_SELECT_INSTANCE_BY_NAME = <<<SQL
SELECT
    i.instance_id id, i.name, i.contact, i.webroot, i.weburl, i.tempdir, i.phpexec, i.app, a.type, v.branch, v.revision, v.type as vcs_type, v.action as last_action, i.state, v.date as last_action_date
FROM
    instance i
INNER JOIN access a
    ON i.instance_id=a.instance_id
LEFT JOIN
    version v ON i.instance_id = v.instance_id
WHERE
    i.name = :name
ORDER BY v.version_id DESC
;
SQL;

    const SQL_SELECT_LAST_INSTANCE = <<<SQL
SELECT
    instance_id id, app
FROM
    instance
WHERE
    app IS NOT NULL
ORDER BY
    instance_id DESC
LIMIT 1
;
SQL;

    const SQLQUERY_UPDATABLE_AND_UPGRADABLE = <<<SQL
SELECT
    i.instance_id id, i.name, i.contact, i.webroot, i.weburl, i.tempdir, i.phpexec, i.app, v.branch, a.type, v.type as vcs_type, v.revision, v.action as last_action, i.state, v.date as last_action_date, v.date_revision as last_revision_date
FROM
    instance i
INNER JOIN access a
    ON i.instance_id=a.instance_id
LEFT JOIN
    version v ON i.instance_id = v.instance_id
LEFT JOIN
    version vmax ON i.instance_id = vmax.instance_id AND v.version_id < vmax.version_id
WHERE
    vmax.version_id IS NULL
SQL;

    const SQL_SELECT_UPDATABLE_INSTANCE = self::SQLQUERY_UPDATABLE_AND_UPGRADABLE . " AND LOWER(v.type) in('svn', 'tarball', 'git', 'src');";

    const SQL_SELECT_UPGRADABLE_INSTANCE = self::SQLQUERY_UPDATABLE_AND_UPGRADABLE . " AND ((LOWER(v.type) in('svn', 'tarball', 'git', 'src') AND v.revision <> '') OR v.version_id IS NULL);";

    const SQL_DUPLICATED_INSTANCE = <<<SQL
SELECT
    i.instance_id
FROM
    instance i
INNER JOIN access a
    ON i.instance_id=a.instance_id
WHERE
    (a.host = :host OR a.host IS NULL) AND i.webroot = :webroot AND i.instance_id <> :id
LIMIT 1
;
SQL;

    const SQL_SELECT_LATEST_VERSION = <<<SQL
SELECT
    version_id id, instance_id, type, branch, date, revision, action, date_revision
FROM
    version
WHERE
    instance_id = :id
ORDER BY
    version_id DESC
LIMIT 1
;
SQL;

    const SQL_SELECT_BACKUP_LOCATION = <<<SQL
SELECT
    location
FROM
    backup
WHERE
    instance_id = :id
;
SQL;

    const SQL_INSERT_INSTANCE = <<<SQL
INSERT OR REPLACE INTO
    instance
    (instance_id, name, contact, webroot, weburl, tempdir, phpexec, app, state)
VALUES
    (:id, :name, :contact, :web, :url, :temp, :phpexec, :app, :state)
;
SQL;

    const SQL_UPDATE_INSTANCE = <<<SQL
UPDATE instance
SET
    name = :name,
    contact = :contact,
    webroot = :web,
    weburl = :url,
    tempdir = :temp
WHERE
    instance_id = :id
;
SQL;

    const SQL_UPDATE_INSTANCE_STATE = <<<SQL
UPDATE instance
SET
    state = :state
WHERE
    instance_id = :id
;
SQL;

    const SQL_INSERT_BACKUP = <<<SQL
INSERT INTO
    backup
    (instance_id, location)
VALUES
    (:id, :loc)
;
SQL;

    const SQL_DELETE_ACCESS = <<<SQL
DELETE FROM
    access
WHERE
    instance_id = :id
;
SQL;

    const SQL_DELETE_BACKUP = <<<SQL
DELETE FROM
    backup
WHERE
    instance_id = :id
;
SQL;

    const SQL_DELETE_INSTANCE = <<<SQL
DELETE FROM
    instance
WHERE
    instance_id = :id
;
SQL;

    const SQL_DELETE_REPORT_CONTENT = <<<SQL
DELETE FROM
    report_content
WHERE
    instance_id = :id OR receiver_id = :id
;
SQL;

    const SQL_DELETE_REPORT_RECEIVER = <<<SQL
DELETE FROM
    report_receiver
WHERE
    instance_id = :id
;
SQL;

    const SQL_DELETE_VERSION = <<<SQL
DELETE FROM
    version
WHERE
    instance_id = :id
;
SQL;

    const SQL_GET_INSTANCE_PROPERTY = <<<SQL
SELECT value FROM
    property
WHERE
    instance_id = :id AND key = :key
;
SQL;

    const SQL_SET_INSTANCE_PROPERTY = <<<SQL
REPLACE INTO
    property
VALUES
    (:id, :key, :value)
;
SQL;

    const SQL_DELETE_ALL_INSTANCE_PROPERTIES = <<<SQL
DELETE FROM
    property
WHERE
    instance_id = :id
;
SQL;

    const SQL_DELETE_FILE_BY_SELECT = <<<SQL
DELETE FROM
    file
WHERE
    version_id
IN (
    SELECT
        version_id
    FROM
        version
    WHERE
        instance_id = :id
);
SQL;

    const SQL_DELETE_PATCH = <<<SQL
DELETE FROM
    patch
WHERE
    instance_id = :id
;
SQL;

    const SQL_SET_BISECT_SESSION = <<<SQL
INSERT OR REPLACE INTO
    bisect_sessions
    (instance_id, bad_commit, good_commit, current_commit, status)
VALUES
    (:instance_id, :bad_commit, :good_commit, :current_commit, :status)
;
SQL;

    const SQL_GET_BISECT_SESSION = <<<SQL
SELECT * FROM 
    bisect_sessions
WHERE
    instance_id = :instance_id AND status = :status
;
SQL;

    const SQL_DELETE_BISECT_SESSION = <<<SQL
DELETE FROM
    bisect_sessions
WHERE
    instance_id = :id
;
SQL;

    const SQL_INSERT_INSTANCE_TAG = <<<SQL
INSERT INTO
    tags
    (instance_id, tag_name, tag_value)
VALUES
    (:id, :tagname, :tagvalue)
;
SQL;

    const SQL_UPDATE_INSTANCE_TAG = <<<SQL
UPDATE tags
SET
    tag_value = :tagvalue
WHERE
    instance_id = :id AND tag_name = :tagname
;
SQL;

    const SQL_DELETE_INSTANCE_TAG = <<<SQL
DELETE FROM
    tags
WHERE
    instance_id = :id AND (COALESCE(:tagname, '') = '' OR tag_name = :tagname)
;
SQL;

    const SQL_GET_INSTANCE_TAGS = <<<SQL
SELECT
    tag_name, tag_value
FROM
    tags
WHERE
    instance_id = :id AND (COALESCE(:tagname, '') = '' OR tag_name = :tagname)
;
SQL;

    private $id;
    public $name;
    public $contact;
    public $webroot;
    public $weburl;
    public $tempdir;
    public $phpexec;
    public $phpversion;
    public $app;
    public $type;
    public $backup_user;
    public $backup_group;
    public $backup_perm;
    public $vcs_type;
    public $state;

    public $selection;
    public $last_action;
    public $last_action_date;
    public $last_revision_date;
    public $revision;

    protected $databaseConfig;

    protected $io;

    private $access = [];
    protected $discovery;

    /** @var VersionControlSystem */
    protected $vcs;

    public function __construct()
    {
        $this->io = App::get('io');
    }

    public function getId()
    {
        return $this->id;
    }

    public static function getInstances($exclude_blank = false)
    {
        $result = query(self::SQL_SELECT_INSTANCE);

        $instances = [];
        while ($instance = $result->fetchObject('TikiManager\Application\Instance')) {
            if ($exclude_blank) {
                if ($instance->hasApplication()) {
                    $instances[$instance->getId()] = $instance;
                }
            } else {
                $instances[$instance->getId()] = $instance;
            }
        }

        return $instances;
    }

    public static function getTikiInstances()
    {
        $allInstances = self::getInstances();

        $tikiInstances = [];
        foreach ($allInstances as $instance) {
            if ($instance->isTiki()) {
                $tikiInstances[$instance->id] = $instance;
            }
        }

        return $tikiInstances;
    }

    public static function getNoTikiInstances()
    {
        $allInstances = self::getInstances();

        $noTikiInstances = [];
        foreach ($allInstances as $instance) {
            if (! $instance->hasApplication()) {
                $noTikiInstances[$instance->id] = $instance;
            }
        }

        return $noTikiInstances;
    }

    /**
     * @param $id
     * @return Instance
     */
    public static function getInstance($id)
    {
        $result = query(self::SQL_SELECT_INSTANCE_BY_ID, [':id' => $id]);
        $instance = $result->fetchObject(Instance::class);

        return $instance;
    }

    /**
     * @param $name
     * @return Instance
     */
    public static function getInstanceByName($name)
    {
        $result = query(self::SQL_SELECT_INSTANCE_BY_NAME, [':name' => $name]);
        $instance = $result->fetchObject('TikiManager\Application\Instance');
        return $instance;
    }

    public static function getLastInstance()
    {
        $result = query(self::SQL_SELECT_LAST_INSTANCE);
        $instance = $result->fetchObject('TikiManager\Application\Instance');
        return $instance;
    }

    public static function getUpdatableInstances($upgrade = null)
    {
        $result = query(self::SQL_SELECT_UPDATABLE_INSTANCE);

        if ($upgrade == "upgrade") {
            $result = query(self::SQL_SELECT_UPGRADABLE_INSTANCE);
        }

        $instances = [];
        while ($instance = $result->fetchObject('TikiManager\Application\Instance')) {
            $instances[$instance->id] = $instance;
        }

        return $instances;
    }
    public static function getUpgradableInstances()
    {
        return self::getUpdatableInstances("upgrade");
    }

    public static function getRestorableInstances()
    {
        $dp = opendir($_ENV['BACKUP_FOLDER']);

        $backups = [];
        $matches = [];
        while (false !== $file = readdir($dp)) {
            if (! preg_match('/^\d+/', $file, $matches)) {
                continue;
            }

            if ($instance = self::getInstance($matches[0])) {
                $backups[$matches[0]] = $instance;
            }
        }

        closedir($dp);
        ksort($backups);
        return $backups;
    }

    public function updateState($state, $action, $reason)
    {
        $prevState = $this->state ?? 'unknown';
        $this->state = $state;
        trim_output("\nInstance {$this->id} state changed from {$prevState} to {$this->state} during {$action}. Reason: {$reason}.\n");
        query(self::SQL_UPDATE_INSTANCE_STATE, [':id' => $this->id, ':state' => $state]);
    }

    public function save()
    {
        $params = [
            ':id' => $this->id,
            ':name' => $this->name,
            ':contact' => $this->contact,
            ':web' => $this->webroot,
            ':url' => $this->weburl,
            ':temp' => $this->tempdir,
            ':phpexec' => $this->phpexec,
            ':app' => $this->app,
            ':state' => $this->state
        ];

        query(self::SQL_INSERT_INSTANCE, $params);

        $rowid = rowid();
        if (! $this->id && $rowid) {
            $this->id = $rowid;
        }

        if (!empty($this->backup_user)) {
            $this->setProp('backup_user', $this->backup_user);
        }
        if (!empty($this->backup_group)) {
            $this->setProp('backup_group', $this->backup_group);
        }
        if (!empty($this->backup_perm)) {
            $this->setProp('backup_perm', $this->backup_perm);
        }
    }

    /**
     * Update the instance information
     */
    public function update()
    {
        $params = [
            ':id'      => $this->id,
            ':name'    => $this->name,
            ':contact' => $this->contact,
            ':web'     => $this->webroot,
            ':url'     => $this->weburl,
            ':temp'    => $this->tempdir
        ];

        query(self::SQL_UPDATE_INSTANCE, $params);

        if (!empty($this->backup_user)) {
            $this->setProp('backup_user', $this->backup_user);
        }
        if (!empty($this->backup_group)) {
            $this->setProp('backup_group', $this->backup_group);
        }
        if (!empty($this->backup_perm)) {
            $this->setProp('backup_perm', $this->backup_perm);
        }
    }

    /**
     * Checks if there is another instance that shares the same details
     * (host and webroot)
     */
    public function hasDuplicate()
    {
        $access = $this->getBestAccess();

        $host = $access->host;
        $port = $access->port;

        if ($host) {
            $host = sprintf('%s:%s', $host, $port);
        }

        global $db;

        $stmt = $db->prepare(self::SQL_DUPLICATED_INSTANCE);
        $stmt->execute([':host' => $host, ':webroot' => $this->webroot, ':id' => $this->id]);
        $result = $stmt->fetchObject();

        if (empty($result->instance_id)) {
            return false;
        }

        return self::getInstance($result->instance_id);
    }

    public function delete()
    {
        query(self::SQL_DELETE_ACCESS, [':id' => $this->id]);
        query(self::SQL_DELETE_BACKUP, [':id' => $this->id]);
        query(self::SQL_DELETE_FILE_BY_SELECT, [':id' => $this->id]);
        query(self::SQL_DELETE_INSTANCE, [':id' => $this->id]);
        query(self::SQL_DELETE_REPORT_CONTENT, [':id' => $this->id]);
        query(self::SQL_DELETE_REPORT_RECEIVER, [ ':id' => $this->id]);
        query(self::SQL_DELETE_VERSION, [':id' => $this->id]);
        query(self::SQL_DELETE_ALL_INSTANCE_PROPERTIES, [':id' => $this->id]);
        query(self::SQL_DELETE_PATCH, [':id' => $this->id]);
        query(self::SQL_DELETE_INSTANCE_TAG, [':id' => $this->id]);
        query(self::SQL_DELETE_BISECT_SESSION, [':id' => $this->id]);
    }

    public function registerAccessMethod($type, $host, $user, $password = null, $port = null)
    {
        if (! $class = Access::getClassFor($type)) {
            return;
        }

        $access = new $class($this);
        $access->host = $host;
        $access->user = $user;
        $access->password = $password;

        if ($port) {
            $access->port = $port;
        }

        if ($access->firstConnect()) {
            $access->save();

            $this->access[] = $access;
            return $access;
        }
    }

    /**
     * @param string $type
     * @return Access
     */
    public function getBestAccess($type = null)
    {
        if (empty($this->access)) {
            $this->access = Access::getAccessFor($this);
        }

        // TODO: Add intelligence as more access types get added
        // types:
        //      scripting
        //      filetransfer
        return reset($this->access);
    }

    public function getWebUrl($relativePath)
    {
        $weburl = rtrim($this->weburl, '/');

        $path = "$weburl/$relativePath";
        $path = str_replace('/./', '/', $path);

        return $path;
    }

    public function getWebPath($relativePath)
    {
        $path = "{$this->webroot}/$relativePath";
        $path = str_replace('/./', '/', $path);

        return $path;
    }

    public function getWorkPath($relativePath)
    {
        return $this->tempdir . DIRECTORY_SEPARATOR . $relativePath;
    }

    public function getProp($key)
    {
        $result = query(self::SQL_GET_INSTANCE_PROPERTY, [':id' => $this->id, ':key' => $key]);
        $result = $result->fetchObject();
        if ($result && $result->value) {
            return $result->value;
        }
    }

    public function setProp($key, $value)
    {
        $result = query(self::SQL_SET_INSTANCE_PROPERTY, [
            ':id' => $this->id,
            ':key' => $key,
            ':value' => $value
        ]);
    }

    public function createWorkPath($access = null)
    {
        if (is_null($access)) {
            $access = $this->getBestAccess('scripting');
        }

        $path = $access->getInterpreterPath();
        $script = sprintf("mkdir('%s', 0777, true);", $this->tempdir);
        $access->createCommand($path, ["-r {$script}"])->run();

        return $this->tempdir;
    }

    public function detectPHP(TikiRequirements $requirements = null)
    {
        $access = $this->getBestAccess('scripting');
        $path = $access->getInterpreterPath($requirements);

        $path_env = getenv('PATH');

        if ($path) {
            $version = $access->getInterpreterVersion($path);

            if ($version >=  50300) {
                $this->phpexec = $path;
                $this->phpversion = intval($version);

                // even passing full path to php binary, we need to fix PATH
                // so scripts like setup.sh can use correct php version
                $bin_folder = dirname($path);
                if (strpos($path_env, $bin_folder) === false) {
                    $access->setenv('PATH', "$bin_folder:$path_env");
                }

                return true;
            }
        }

        throw new Exception("No suitable php interpreter was found on {$this->name} instance");
    }

    public function detectSVN()
    {
        $access = $this->getBestAccess('scripting');
        $path = $access->getSVNPath();

        if (strlen($path) > 0) {
            return $path;
        }

        return false;
    }

    public function detectDistribution()
    {
        $access = $this->getBestAccess('scripting');
        $path = $this->phpexec ?? $access->getInterpreterPath();
        return $access->getDistributionName($path);
    }

    public function getExtensions()
    {
        $access = $this->getBestAccess('scripting');
        $content = $access->runPHP($_ENV['TRIM_ROOT'] . '/scripts/get_extensions.php');
        $modules = explode("\n", $content);

        return $modules;
    }

    public function findApplication()
    {
        foreach ($this->getApplications() as $app) {
            if ($app->isInstalled()) {
                $app->registerCurrentInstallation();
                return $app;
            }
        }

        return null;
    }

    public function createVersion()
    {
        return new Version($this->getId());
    }

    /**
     * @return Version
     * @throws \Exception
     */
    public function updateVersion()
    {
        $version = $this->createVersion();
        $app = $this->getApplication();
        $version->type = $app->getInstallType(true);

        if (empty($version->type)) {
            throw new \Exception('Unable to update version. This is a blank instance');
        }

        $version->branch = $app->getBranch(true);
        $version->date = $app->getUpdateDate();
        $version->revision = $app->getRevision($this->webroot);
        $version->date_revision = $app->getDateRevision($this->webroot);
        $version->save();

        return $version;
    }

    public function getLatestVersion()
    {
        $result = query(self::SQL_SELECT_LATEST_VERSION, [':id' => $this->id]);
        $object = $result->fetchObject('TikiManager\Application\Version', [$this->id]);

        return $object;
    }

    /**
     * Modern in this context means it uses composer and has console.php for shell access which arrived in Tiki 11,
     * although this may need to be changed to 12 if 11 is proved to be unreliable in these respects
     *
     * @return bool
     */
    public function hasConsole()
    {
        return $this->getBestAccess()->fileExists('console.php');
    }

    /**
     * @return bool
     */
    public function hasApplication()
    {
        return ! empty($this->app);
    }

    public function isTiki()
    {
        return $this->app === 'tiki';
    }

    /**
     * @return Application | false
     */
    public function getApplication()
    {
        if (empty($this->app)) {
            return false;
        }

        $class = ucfirst($this->app);
        if (! class_exists($class) && is_subclass_of('TikiManager\Application\\'.$class, 'TikiManager\Application\Application')) {
            $class = 'TikiManager\Application\\'.$class;
            return new $class($this);
        }

        return false;
    }

    /**
     * @param bool $direct ()
     * @param bool $full Full backup or partial (backing up only changes against VCS system)
     * @param bool $onlyCode
     * @return bool|string
     * @throws Exception\FolderPermissionException
     */
    public function backup($direct = false, $full = true, $onlyCode = false)
    {
        $backup = new Backup($this, $direct, $full, $onlyCode);

        if ($this->type === 'local' && $this->detectDistribution() === 'ClearOS') {
            $backup->setArchiveSymlink(dirname($this->webroot) . '/backup');
        }

        return $backup->create($direct);
    }

    /**
     * Restore instance
     *
     * @param $srcInstance
     * @param $archive
     * @param bool $clone
     * @param bool $checksumCheck
     * @param bool $direct
     * @param bool $onlyData
     * @param bool $onlyCode
     * @param array $options
     * @param bool $skipSystemConfigurationCheck
     * @param int $allowCommonParents
     *
     * @throws FolderPermissionException
     * @throws RestoreErrorException
     */
    public function restore($srcInstance, $archive, $clone = false, $checksumCheck = false, $direct = false, $onlyData = false, $onlyCode = false, $options = [], $skipSystemConfigurationCheck = false, $allowCommonParents = 0)
    {
        $restore = new Restore($this, $direct, $onlyData, $skipSystemConfigurationCheck, $allowCommonParents);

        $restore->lock();
        $restore->setProcess($clone);

        if ($direct) {
            $restore->setRestoreRoot(dirname($archive));
            $restore->setRestoreDirname(basename($archive));
        }

        $message = "Restoring files from '{$archive}' into {$this->name}...";

        if ($direct) {
            $message = "Restoring files from '{$srcInstance->name}' into {$this->name}...";
        }

        $this->io->writeln($message . ' <fg=yellow>[may take a while]</>');

        $restore->setSourceInstance($srcInstance);
        $restore->restoreFiles($archive);

        // Redetect the VCS type
        $this->vcs_type = $this->getDiscovery()->detectVcsType();

        $this->app = $srcInstance->app;
        $this->save();

        if (! $onlyCode) {
            $this->io->writeln('Restoring database...');
            $database_dump = $restore->getRestoreFolder() . "/database_dump.sql";

            $databaseConfig = $this->getDatabaseConfig();
            if ($databaseConfig) {
                try {
                    $app = $this->getApplication();
                    $app->restoreDatabase($databaseConfig, $database_dump, $clone);
                } catch (\Exception $e) {
                    $restore->unlock();
                    throw $e;
                }
            } else {
                $this->io->error('Database config not available (db/local.php), so the database can\'t be restored.');
            }
        }

        $discovery = $this->getDiscovery();

        // Redetect the VCS type in case of change
        $this->vcs_type = $discovery->detectVcsType();

        if (!$this->findApplication()) { // a version is created in this call
            $restore->unlock();
            $this->io->error('Something went wrong with restore. Unable to read application details.');
            return;
        }

        $version = null;
        $oldVersion = $this->getLatestVersion();

        // Pick version created in findApplication
        if (!$oldVersion || $clone) {
            $version = $this->getLatestVersion();
        }

        if (!$version) {
            $version = $this->createVersion();
            $version->type = is_object($oldVersion) ? $oldVersion->type : null;
            $version->branch = is_object($oldVersion) ? $oldVersion->branch : null;
            $version->date = is_object($oldVersion) ? $oldVersion->date : null;
        }

        // Update version with the correct action
        $version->action = $clone ? 'clone' : 'restore';
        $version->save();

        $this->io->writeln('<info>Detected Tiki ' . $version->branch . ' using ' . $version->type . '</info>');

        if ($this->vcs_type == 'svn') {
            /** @var Svn $svn */
            $svn = VersionControlSystem::getVersionControlSystem($this);
            $svn->ensureTempFolder($this->webroot);
        }

        if ($this->app == 'tiki' && ! $onlyData) {
            $this->io->writeln("Applying patches to {$this->name}...");
            foreach (Patch::getPatches($this->getId()) as $patch) {
                $patch->delete();
            }
            foreach (Patch::getPatches($srcInstance->getId()) as $patch) {
                $patch->id = null;
                $patch->instance = $this->getId();
                $patch->save();
            }

            $this->getApplication()->applyPatches();
            $this->getApplication()->installComposerDependencies();
            $this->getApplication()->installNodeJsDependencies();
            $this->getApplication()->installTikiPackages();

            $this->io->writeln("Fixing permissions for {$this->name}");
            $this->getApplication()->fixPermissions();
        }

        if ($checksumCheck && ! $onlyData) {
            $this->io->writeln('Collecting files checksum from instance...');
            $version->collectChecksumFromInstance($this);
        }

        if ($onlyData) {
            $options['applying-patch'] = true;
            $this->getApplication()->postInstall($options);
        }

        if (!$direct) {
            $restore->removeRestoreRootFolder();
        }

        $restore->unlock();
    }

    public function getExtraBackups()
    {
        $result = query(self::SQL_SELECT_BACKUP_LOCATION, [':id' => $this->id]);

        $list = [];
        while ($str = $result->fetchColumn()) {
            $list[] = $str;
        }

        return $list;
    }

    public function setExtraBackups($paths)
    {
        query(self::SQL_DELETE_BACKUP, [':id' => $this->id]);

        foreach ($paths as $path) {
            if (! empty($path)) {
                query(self::SQL_INSERT_BACKUP, [':id' => $this->id, ':loc' => $path]);
            }
        }
    }

    public function getArchives()
    {
        try {
            $backup = new Backup($this);
            return $backup->getArchives();
        } catch (\Exception $e) {
            // Write the error in Tiki Manager output file
            // This should be replaced with LoggerInterface
            trim_output($e->getMessage(), ['instance_id' => $this->id]);
        }

        return  [];
    }

    public function reduceBackups($maxBackups = 0)
    {
        Archive::cleanup($this->id, $this->name, $maxBackups);
    }

    public function isLocked()
    {
        $access = $this->getBestAccess('scripting');
        $base_htaccess = $_ENV['TRIM_ROOT'] . '/scripts/maintenance.htaccess';
        $curr_htaccess = $this->getWebPath('.htaccess');

        return $access->fileExists($curr_htaccess)
            && file_get_contents($base_htaccess) === $access->fileGetContents($curr_htaccess);
    }

    public function lock()
    {
        if ($this->isLocked()) {
            return true;
        }
        $this->io->writeln('Locking website...');

        $access = $this->getBestAccess('scripting');
        $path = $this->phpexec ?? $access->getInterpreterPath();
        $access->uploadFile($_ENV['TRIM_ROOT'] . '/scripts/maintenance.php', 'maintenance.php');

        $access->shellExec(sprintf('%s -r "touch(\'maintenance.php\');"', $path));

        if ($access->fileExists($this->getWebPath('.htaccess'))) {
            $access->moveFile('.htaccess', '.htaccess.bak');
        }

        $access->uploadFile($_ENV['TRIM_ROOT'] . '/scripts/maintenance.htaccess', '.htaccess');
        return $this->isLocked();
    }

    public function unlock()
    {
        if (!$this->isLocked()) {
            return true;
        }

        $this->io->writeln('Unlocking website...');
        $access = $this->getBestAccess('scripting');
        $access->deleteFile('.htaccess');
        $access->deleteFile('maintenance.php');

        if ($access->fileExists('.htaccess.bak')) {
            $access->moveFile('.htaccess.bak', '.htaccess');
        }

        if (!$access->fileExists('.htaccess')) {
            $this->configureHtaccess();
        }

        return !$this->isLocked();
    }


    public function configureHtaccess()
    {
        $access = $this->getBestAccess('scripting');

        if (!$access->fileExists('_htaccess')) {
            return false;
        }

        // Try symlink
        if (!$access->fileExists('.htaccess') && method_exists($access, 'shellExec')) {
            $access->shellExec('ln -s _htaccess .htaccess');
        }

        // Copy
        if (!$access->fileExists('.htaccess')) {
            $access->copyFile('_htaccess', '.htaccess');
        }

        return $access->fileExists('.htaccess');
    }

    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }
    }

    /**
     * Get instance application branch
     *
     * @return mixed
     */
    public function getBranch()
    {

        if ($this->app == 'tiki') {
            return $this->getApplication()->getBranch();
        }

        return null;
    }

    /**
     * Get instance application revision
     *
     * @return mixed
     */
    public function getRevision()
    {

        if ($this->app == 'tiki') {
            return $this->getApplication()->getRevision();
        }

        return null;
    }

    /**
     * Get instance application date revision
     * @return mixed|string|null
     */
    public function getDateRevision()
    {
        if ($this->app == 'tiki') {
            return $this->getApplication()->getDateRevision();
        }

        return null;
    }
    /**
     * @param Database|array $config
     */
    public function setDatabaseConfig($config)
    {
        $this->databaseConfig = $config;
    }

    /**
     * @return Database|array
     */
    public function getDatabaseConfig()
    {
        return $this->databaseConfig ?? $this->loadDatabaseConfig() ?? null;
    }

    /**
     * Load database config from file (db/local.php)
     * @return Database|null
     */
    private function loadDatabaseConfig()
    {
        $access = $this->getBestAccess('scripting');
        $remoteFile = "{$this->webroot}/db/local.php";

        if (!$access || !$access->fileExists($remoteFile)) {
            return null;
        }

        $localFile = $access->downloadFile($remoteFile);
        $dbUser = Database::createFromConfig($this, $localFile);
        unlink($localFile);

        if ($dbUser instanceof Database) {
            $this->setDatabaseConfig($dbUser);
            return $dbUser;
        }

        return null;
    }

    public function getCompatibleVersions(bool $withBlank = null)
    {

        $apps = $this->getApplications();
        $selection = getEntries($apps, 0);
        $app = reset($selection);

        if (! is_null($withBlank)) {
            return $app->getCompatibleVersions($withBlank);
        } else {
            return $app->getCompatibleVersions();
        }
    }

    /**
     * @return Discovery
     */
    public function getDiscovery(): Discovery
    {
        if (!$this->discovery) {
            $access = $this->getBestAccess('scripting');
            $this->discovery = Discovery::createInstance($this, $access);
        }

        return $this->discovery;
    }

    /**
     * @return array
     */
    public function getApplications(): array
    {
        return Application::getApplications($this);
    }

    /**
     * @param Application $app
     * @param Version $version
     * @param bool $checksumCheck
     * @throws \Exception
     */
    public function installApplication(Application $app, Version $version, $checksumCheck = false, $revision = null)
    {
        $app->install($version, $checksumCheck, $revision);

        if ($app->requiresDatabase()) {
            $this->database()->setupConnection();
            $dbConfig = $this->getDatabaseConfig();
            $app->setupDatabase($dbConfig);
            $this->reindex();
        }
    }

    public function database()
    {
        return new Database($this);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function testDbConnection(): bool
    {
        $dbRoot = $this->getDatabaseConfig();

        if (!$dbRoot) {
            throw new \Exception('Database configuration file not found.');
        }

        return $dbRoot->testConnection();
    }

    public function getVersionControlSystem()
    {
        if (!$this->vcs) {
            $this->vcs = VersionControlSystem::getVersionControlSystem($this);
        }

        return $this->vcs;
    }

    public function reindex(): bool
    {
        $envExecTimeout = $_ENV['COMMAND_EXECUTION_TIMEOUT'];
        $_ENV['COMMAND_EXECUTION_TIMEOUT'] = $this->getMaxExecTimeout($envExecTimeout);

        $access = $this->getBestAccess('scripting');
        $cmd = "{$this->phpexec} -q -d memory_limit=256M console.php index:rebuild --log";
        $cmd = $access->executeWithPriorityParams($cmd);
        $command = new Command($cmd);
        $data = $access->runCommand($command);
        $output = $data->getStdoutContent();
        $_ENV['COMMAND_EXECUTION_TIMEOUT'] = $envExecTimeout;
        return str_contains($output, 'Rebuilding index done') && $data->getReturn() == 0;
    }

    public function getCronManager(): CrontabManager
    {
        return new CrontabManager($this);
    }

    public function revert()
    {
        if ($this->vcs_type != 'svn' && $this->vcs_type != 'git') {
            $this->io->error(sprintf("Instance %s is not a version controlled instance and cannot be reverted.", $this->name));
            return;
        }
        $this->getVersionControlSystem()->revert($this->webroot);
    }

    public function updateOrSaveBisectSession($sessionDetails)
    {
        try {
            query(self::SQL_SET_BISECT_SESSION, $sessionDetails);
            $app = $this->getApplication();
            $app->installComposerDependencies();
            $app->installNodeJsDependencies();
            $app->runDatabaseUpdate();
        } catch (\Exception $e) {
            throw new \Exception("Error setting bisect session: " . $e->getMessage());
        }
    }

    public function getOnGoingBisectSession()
    {
        $result = query(self::SQL_GET_BISECT_SESSION, [':instance_id' => $this->getId(), ':status' => 'in_progress']);
        return $result->fetchObject();
    }

    /**
     * Check if execution timeout is higher in tiki instance preference
     *
     * @param string $timeout
     * @return string
     */
    private function getMaxExecTimeout($timeout) : string
    {
        $instanceExecTimeout = $this->getApplication()->getPref('allocate_time_unified_rebuild');
        if ($instanceExecTimeout && (int) $instanceExecTimeout > (int) $timeout) {
            return $instanceExecTimeout;
        }

        return $timeout;
    }

    public function addOrUpdateInstanceTag(string $tagName, string $tagValue): bool
    {
        $params = [':id' => $this->id, ':tagname' => $tagName, ':tagvalue' => $tagValue];
        $tag = $this->getInstanceTags($tagName);
        if (count($tag)) {
            $result = query(self::SQL_UPDATE_INSTANCE_TAG, $params);
        } else {
            $result = query(self::SQL_INSERT_INSTANCE_TAG, $params);
        }
        return $result && $result->rowCount();
    }

    public function deleteInstanceTag(string $tagName): bool
    {
        $params = [':id' => $this->id, ':tagname' => $tagName];
        $results = query(self::SQL_DELETE_INSTANCE_TAG, $params);
        return $results && $results->rowCount();
    }

    public function getInstanceTags(?string $tagName): array
    {
        $params = [':id' => $this->id];
        if ($tagName) {
            $params[':tagname'] = $tagName;
        }

        $result = query(self::SQL_GET_INSTANCE_TAGS, $params);
        $tags = $result->fetchAll();

        return array_map(function ($item, $index) use ($tagName) {
            $row = ['tagname' => $item['tag_name'], 'tagvalue' => $item['tag_value']];
            if (!$tagName) {
                $row = ['no' => $index + 1] + $row;
            }
            return $row;
        }, $tags, array_keys($tags));
    }

    public function isInstanceProtected(): bool
    {
        $tags = $this->getInstanceTags('sys_db_protected');
        foreach ($tags as $tag) {
            if ($tag['tagname'] === 'sys_db_protected' && $tag['tagvalue'] === 'y') {
                return true;
            }
        }
        return false;
    }
}
