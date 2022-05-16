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
    i.instance_id id, i.name, i.contact, i.webroot, i.weburl, i.tempdir, i.phpexec, i.app, a.type, v.branch, v.revision, v.type as vcs_type, v.action as last_action, v.date as last_action_date
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
    i.instance_id id, i.name, i.contact, i.webroot, i.weburl, i.tempdir, i.phpexec, i.app, a.type, v.branch, v.revision, v.type as vcs_type, v.action as last_action, v.date as last_action_date
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

    const SQL_SELECT_UPDATABLE_INSTANCE = <<<SQL
SELECT
    i.instance_id id, i.name, i.contact, i.webroot, i.weburl, i.tempdir, i.phpexec, i.app, v.branch, a.type, v.type as vcs_type, v.revision, v.action as last_action, v.date as last_action_date
FROM
    instance i
INNER JOIN access a
    ON i.instance_id=a.instance_id
INNER JOIN
    version v ON i.instance_id = v.instance_id
INNER JOIN (
    SELECT
        MAX(version_id) version
    FROM
        version
    GROUP BY
        instance_id
    ) t ON t.version = v.version_id
WHERE
    LOWER(v.type) in('svn', 'tarball', 'git', 'src')
;
SQL;

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
    version_id id, instance_id, type, branch, date, revision, action
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
    (instance_id, name, contact, webroot, weburl, tempdir, phpexec, app)
VALUES
    (:id, :name, :contact, :web, :url, :temp, :phpexec, :app)
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

    public $selection;
    public $last_action;
    public $last_action_date;
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
                if ($instance->getApplication()) {
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
            if ($instance->getApplication() instanceof Tiki) {
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
            if (! $instance->getApplication()) {
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
        $instance = $result->fetchObject('TikiManager\Application\Instance');
        return $instance;
    }

    public static function getUpdatableInstances()
    {
        $result = query(self::SQL_SELECT_UPDATABLE_INSTANCE);

        $instances = [];
        while ($instance = $result->fetchObject('TikiManager\Application\Instance')) {
            $instances[$instance->id] = $instance;
        }

        return $instances;
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
                    $access->setenv('PATH', "${bin_folder}:${path_env}");
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
     * @return bool|string
     * @throws Exception\FolderPermissionException
     */
    public function backup($direct = false, $full = true)
    {
        $backup = new Backup($this, $direct, $full);

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
     * @throws Exception\FolderPermissionException
     * @throws Exception\RestoreErrorException
     */
    public function restore($srcInstance, $archive, $clone = false, $checksumCheck = false, $direct = false)
    {
        $restore = new Restore($this, $direct);
        $restore->lock();

        $message = "Restoring files from '{$archive}' into {$this->name}...";

        if ($direct) {
            $message = "Restoring files from '{$srcInstance->name}' into {$this->name}...";
        }

        $this->io->writeln($message . ' <fg=yellow>[may take a while]</>');

        $restore->setProcess($clone);

        if ($direct) {
            $restore->setRestoreRoot(dirname($archive));
            $restore->setRestoreDirname(basename($archive));
            $restore->setSourceInstance($srcInstance);
        }

        $restore->restoreFiles($archive);

        // Redetect the VCS type
        $this->vcs_type = $this->getDiscovery()->detectVcsType();

        $this->app = $srcInstance->app;
        $this->save();

        $this->io->writeln('Restoring database...');
        $database_dump = $restore->getRestoreFolder() . "/database_dump.sql";

        $version = null;
        $oldVersion = $this->getLatestVersion();

        $discovery = $this->getDiscovery();

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

        // Redetect the VCS type in case of change
        $this->vcs_type = $discovery->detectVcsType();

        if (!$this->findApplication()) { // a version is created in this call
            $restore->unlock();
            $this->io->error('Something went wrong with restore. Unable to read application details.');
            return;
        }

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

        if ($this->app == 'tiki') {
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
            $this->getApplication()->installTikiPackages();

            $this->io->writeln("Fixing permissions for {$this->name}");
            $this->getApplication()->fixPermissions();
        }

        if ($checksumCheck) {
            $this->io->writeln('Collecting files checksum from instance...');
            $version->collectChecksumFromInstance($this);
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
            trim_output($e->getMessage());
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

    public function getCompatibleVersions()
    {

        $apps = $this->getApplications();
        $selection = getEntries($apps, 0);
        $app = reset($selection);

        return $app->getCompatibleVersions();
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
    public function installApplication(Application $app, Version $version, $checksumCheck = false)
    {
        $app->install($version, $checksumCheck);

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
        $access = $this->getBestAccess('scripting');
        $command = new Command("{$this->phpexec} -q -d memory_limit=256M console.php index:rebuild --log");
        $data = $access->runCommand($command);
        $output = $data->getStdoutContent();
        return str_contains($output, 'Rebuilding index done') && $data->getReturn() == 0;
    }

    public function getCronManager(): CrontabManager
    {
        return new CrontabManager($this);
    }

    public function revert()
    {
        if ($this->vcs_type != 'svn' && $this->vcs_type != 'git') {
            $this->io->error(sprintf("Instance %s is not a version controlled instance and cannot be reverted."));
            return;
        }
        $this->getVersionControlSystem()->revert($this->webroot);
    }
}
