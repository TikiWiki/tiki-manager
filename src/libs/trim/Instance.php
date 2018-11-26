<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

define('SQL_SELECT_INSTANCE', "
SELECT
    i.instance_id id, i.name, i.contact, i.webroot, i.weburl, i.tempdir, i.phpexec, i.app, a.type, v.branch
FROM
    instance i
INNER JOIN access a
    ON i.instance_id=a.instance_id
LEFT JOIN
    version v ON i.instance_id = v.instance_id
;");

define('SQL_SELECT_INSTANCE_BY_ID', "
SELECT
    i.instance_id id, i.name, i.contact, i.webroot, i.weburl, i.tempdir, i.phpexec, i.app, a.type, v.branch
FROM
    instance i
INNER JOIN access a
    ON i.instance_id=a.instance_id
LEFT JOIN
    version v ON i.instance_id = v.instance_id
WHERE
    i.instance_id = :id
;");

define('SQL_SELECT_UPDATABLE_INSTANCE', "
SELECT
    instance.instance_id id, name, contact, webroot, weburl, tempdir, phpexec, app, version.branch
FROM
    instance
INNER JOIN
    version ON instance.instance_id = version.instance_id
INNER JOIN (
    SELECT
        MAX(version_id) version
    FROM
        version
    GROUP BY
        instance_id
    ) t ON t.version = version.version_id
WHERE
    type = 'svn' OR type = 'tarball'
;");

define('SQL_SELECT_LATEST_VERSION', "
SELECT
    version_id id, instance_id, type, branch, date
FROM
    version
WHERE
    instance_id = :id
ORDER BY
    version_id DESC
LIMIT 1;");

define('SQL_SELECT_BACKUP_LOCATION', "
SELECT
    location
FROM
    backup
WHERE
    instance_id = :id
");

define('SQL_INSERT_INSTANCE', "
INSERT OR REPLACE INTO
    instance
    (instance_id, name, contact, webroot, weburl, tempdir, phpexec, app)
VALUES
    (:id, :name, :contact, :web, :url, :temp, :phpexec, :app)
;");

define('SQL_UPDATE_INSTANCE', "
UPDATE instance
SET
    name = :name,
    contact = :contact,
    webroot = :web,
    weburl = :url,
    tempdir = :temp
WHERE
    instance_id = :id
;");

define('SQL_INSERT_BACKUP', "
INSERT INTO
    backup
    (instance_id, location)
VALUES
    (:id, :loc)
;");

define('SQL_DELETE_ACCESS', "
DELETE FROM
    access
WHERE
    instance_id = :id
;");

define('SQL_DELETE_BACKUP', "
DELETE FROM
    backup
WHERE
    instance_id = :id
;");

define('SQL_DELETE_FILE_BY_SELECT', "
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
);");

define('SQL_DELETE_INSTANCE', "
DELETE FROM
    instance
WHERE
    instance_id = :id
;");

define('SQL_DELETE_REPORT_CONTENT', "
DELETE FROM
    report_content
WHERE
    instance_id = :id OR receiver_id = :id
;");

define('SQL_DELETE_REPORT_RECEIVER', "
DELETE FROM
    report_receiver
WHERE
    instance_id = :id
;");

define('SQL_DELETE_VERSION', "
DELETE FROM
    version
WHERE
    instance_id = :id
;");

define('SQL_GET_INSTANCE_PROPERTY', "
SELECT value FROM
    property
WHERE
    instance_id = :id AND key = :key
;");

define('SQL_SET_INSTANCE_PROPERTY', "
REPLACE INTO
    property
VALUES
    (:id, :key, :value)
;");

define('SQL_DELETE_ALL_INSTANCE_PROPERTIES', "
DELETE FROM
    property
WHERE
    instance_id = :id;
;");


class Instance
{
    const TYPES = 'local,ftp,ssh';

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

    private $access = [];

    function getId()
    {
        return $this->id;
    }

    static function getInstances($exclude_blank = false)
    {
        $result = query(SQL_SELECT_INSTANCE);

        $instances = [];
        while ($instance = $result->fetchObject('Instance')) {
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

    static function getInstance($id)
    {
        $result = query(SQL_SELECT_INSTANCE_BY_ID, [':id' => $id]);
        $instance = $result->fetchObject('Instance');
        return $instance;
    }

    static function getUpdatableInstances()
    {
        $result = query(SQL_SELECT_UPDATABLE_INSTANCE);

        $instances = [];
        while ($instance = $result->fetchObject('Instance')) {
            $instances[$instance->id] = $instance;
        }

        return $instances;
    }

    static function getRestorableInstances()
    {
        $dp = opendir(BACKUP_FOLDER);

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
        return $backups;
    }

    function save()
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

        query(SQL_INSERT_INSTANCE, $params);

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

        query(SQL_UPDATE_INSTANCE, $params);

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

    function delete()
    {
        query(SQL_DELETE_ACCESS, [':id' => $this->id]);
        query(SQL_DELETE_BACKUP, [':id' => $this->id]);
        query(SQL_DELETE_FILE_BY_SELECT, [':id' => $this->id]);
        query(SQL_DELETE_INSTANCE, [':id' => $this->id]);
        query(SQL_DELETE_REPORT_CONTENT, [':id' => $this->id]);
        query(SQL_DELETE_REPORT_RECEIVER, [ ':id' => $this->id]);
        query(SQL_DELETE_VERSION, [':id' => $this->id]);
        query(SQL_DELETE_ALL_INSTANCE_PROPERTIES, [':id' => $this->id]);
    }

    function registerAccessMethod($type, $host, $user, $password = null, $port = null)
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

    function getBestAccess($type)
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

    function getWebUrl($relativePath)
    {
        $weburl = rtrim($this->weburl, '/');

        $path = "$weburl/$relativePath";
        $path = str_replace('/./', '/', $path);

        return $path;
    }

    function getWebPath($relativePath)
    {
        $path = "{$this->webroot}/$relativePath";
        $path = str_replace('/./', '/', $path);

        return $path;
    }

    function getWorkPath($relativePath)
    {
        return "{$this->tempdir}/$relativePath";
    }

    function getProp($key)
    {
        $result = query(SQL_GET_INSTANCE_PROPERTY, [':id' => $this->id, ':key' => $key]);
        $result = $result->fetchObject();
        if ($result && $result->value) {
            return $result->value;
        }
    }

    function setProp($key, $value)
    {
        $result = query(SQL_SET_INSTANCE_PROPERTY, [
            ':id' => $this->id,
            ':key' => $key,
            ':value' => $value
        ]);
    }

    function createWorkPath($access = null)
    {
        if (is_null($access)) {
            $access = $this->getBestAccess('scripting');
        }

        echo $access->shellExec(
            "mkdir -p {$this->tempdir}"
        );

        return $this->tempdir;
    }

    function getPHPVersion()
    {
        $access = $this->getBestAccess('scripting');
        $path = $access->getInterpreterPath($this);
        $version = $access->shellExec("{$path} -r 'echo phpversion();'");
        return $version;
    }

    function detectPHP()
    {
        $access = $this->getBestAccess('scripting');
        $path = $access->getInterpreterPath($this);

        $path_env = getenv('PATH');

        if (strlen($path) > 0) {
            $version = $access->getInterpreterVersion($path);
            $this->phpversion = intval($version);
            if ($version <  50300) {
                return false;
            }

            $this->phpexec = $path;
            $this->save();

            // even passing full path to php binary, we need to fix PATH
            // so scripts like setup.sh can use correct php version
            $bin_folder = dirname($path);
            if (strpos($path_env, $bin_folder) === false) {
                $access->setenv('PATH', "${bin_folder}:${path_env}");
            }

            return $version;
        }

        error("No suitable php interpreter was found on {$this->name} instance");
        exit(1);
    }

    function detectSVN()
    {
        $access = $this->getBestAccess('scripting');
        $path = $access->getSVNPath();

        if (strlen($path) > 0) {
            return $path;
        }

        return false;
    }

    function detectDistribution()
    {
        $access = $this->getBestAccess('scripting');
        $path = $access->getInterpreterPath();
        return $access->getDistributionName($path);
    }

    function getExtensions()
    {
        $access = $this->getBestAccess('scripting');
        $content = $access->runPHP(TRIM_ROOT . '/scripts/get_extensions.php');
        $modules = explode("\n", $content);

        return $modules;
    }

    function findApplication()
    {
        foreach (Application::getApplications($this) as $app) {
            if ($app->isInstalled()) {
                $app->registerCurrentInstallation();
                return $app;
            }
        }

        return null;
    }

    function createVersion()
    {
        return new Version($this->getId());
    }

    function getLatestVersion()
    {
        $result = query(SQL_SELECT_LATEST_VERSION, [':id' => $this->id]);
        $object = $result->fetchObject('Version', [$this]);

        return $object;
    }

    /**
     * Modern in this context means it uses composer and has console.php for shell access which arrived in Tiki 11,
     * although this may need to be changed to 12 if 11 is proved to be unreliable in these respects
     *
     * @return bool
     */
    function hasConsole()
    {
        $current = $this->getLatestVersion();
        $hasConsole = $current->branch === 'trunk'
            || (
                preg_match('/(\d+)\.?/', $current->branch, $matches)
                && floatval($matches[1]) >= 11
            );
        return $hasConsole;
    }

    function getApplication()
    {
        if (empty($this->app)) {
            return false;
        }

        $class = 'Application_' . ucfirst($this->app);

        $dir = TRIM_ROOT . '/src/appinfo';
        if (! class_exists($class)) {
            require_once "$dir/{$this->app}.php";
        }

        return new $class($this);
    }

    function backup()
    {
        $backup = new Backup($this);

        if ($this->detectDistribution() === 'ClearOS') {
            $backup->setArchiveSymlink(dirname($this->webroot) . '/backup');
        }

        $tar = $backup->create();
        return $tar;
    }

    function restore($src_app, $archive, $clone = false)
    {
        $access = $this->getBestAccess('scripting');

        info("Restoring files from '{$archive}' into {$this->name}");
        $restore = new Restore($this);
        $restore->restoreFiles($archive);

        $this->app = $src_app;
        $this->save();
        $database_dump = $restore->getRestoreFolder() . "/database_dump.sql";

        $version = null;
        $oldVersion = $this->getLatestVersion();

        perform_database_setup($this, $database_dump);
        perform_instance_installation($this); // a version is created in this call

        if (!$oldVersion) {
            $version = $this->getLatestVersion();
        }

        if (!$version) {
            $version = $this->createVersion();
            $version->type = is_object($oldVersion) ? $oldVersion->type : null;
            $version->branch = is_object($oldVersion) ? $oldVersion->branch : null;
            $version->date = is_object($oldVersion) ? $oldVersion->date : null;
            $version->save();
        }

        if ($this->app == 'tiki') {
            info("Fixing permissions for {$this->name}");
            $this->getApplication()->fixPermissions();
        }

        $version->collectChecksumFromInstance($this);
        echo $access->shellExec(
            "rm -Rf {$this->tempdir}/restore"
        );
    }

    function getExtraBackups()
    {
        $result = query(SQL_SELECT_BACKUP_LOCATION, [':id' => $this->id]);

        $list = [];
        while ($str = $result->fetchColumn()) {
            $list[] = $str;
        }

        return $list;
    }

    function setExtraBackups($paths)
    {
        query(SQL_DELETE_BACKUP, [':id' => $this->id]);

        foreach ($paths as $path) {
            if (! empty($path)) {
                query(SQL_INSERT_BACKUP, [':id' => $this->id, ':loc' => $path]);
            }
        }
    }

    function getArchives()
    {
        $backup = new Backup($this);
        return $backup->getArchives();
    }

    function isLocked()
    {
        $access = $this->getBestAccess('scripting');
        $base_htaccess = TRIM_ROOT . '/scripts/maintenance.htaccess';
        $curr_htaccess = $this->getWebPath('.htaccess');

        return $access->fileExists($curr_htaccess)
            && file_get_contents($base_htaccess) === $access->fileGetContents($curr_htaccess);
    }

    function lock()
    {
        if ($this->isLocked()) {
            return true;
        }
        info('Locking website...');

        $access = $this->getBestAccess('scripting');
        $access->uploadFile(TRIM_ROOT . '/scripts/maintenance.php', 'maintenance.php');
        $access->shellExec('touch maintenance.php');

        if ($access->fileExists($this->getWebPath('.htaccess'))) {
            $access->moveFile('.htaccess', '.htaccess.bak');
        }

        $access->uploadFile(TRIM_ROOT . '/scripts/maintenance.htaccess', '.htaccess');
        return $this->isLocked();
    }

    function unlock()
    {
        if (!$this->isLocked()) {
            return true;
        }

        info('Unlocking website...');
        $access = $this->getBestAccess('scripting');
        $access->deleteFile('.htaccess');
        $access->deleteFile('maintenance.php');

        if ($access->fileExists('.htaccess.bak')) {
            $access->moveFile('.htaccess.bak', '.htaccess');
        }

        return !$this->isLocked();
    }

    function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }
    }
}
