<?php

class Audit_Checksum {
    const SQL_SELECT_FILE_MAP =
        "SELECT
            path, hash
        FROM
            file
        WHERE
            version_id = :v
            ;";

    const SQL_SELECT_FILE_COUNT_BY_VERSION =
        "SELECT
            COUNT(*)
        FROM
            file
        WHERE
            version_id = :id
        ;";

    const SQL_INSERT_FILE =
        "INSERT INTO
            file
            (version_id, path, hash)
        VALUES
            (:version, :path, :hash)
        ;";

    const SQL_INSERT_FILE_REPLICATE =
        "INSERT INTO
            file
            (version_id, path, hash)
            SELECT
                :new, path, hash
            FROM
                file
            WHERE
                version_id = :old
        ;";

    const SQL_DELETE_FILE =
        "DELETE FROM
            file
        WHERE
            path = :p and version_id = :v
        ;";

    const SQL_DELETE_FILE_BY_SELECT =
        "DELETE FROM
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
        );";

    const CHECKSUM_IGNORE_PATTERN = '#(^\./temp|/\.git|/\.svn)/#';

    private $instance;
    private $access;

    public function __construct($instance)
    {
        $this->instance = $instance;
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function getAccess()
    {
        return $this->instance->getBestAccess('scripting');
    }

    public function getApp()
    {
        return $this->instance->getApplication();
    }

    public function hasChecksums($version_id)
    {
        $args = array(':id' => $version_id);
        $result = query(self::SQL_SELECT_FILE_COUNT_BY_VERSION, $args);
        return ($result->fetchColumn() > 0);
    }

    public function getChecksums($version_id)
    {
        $map = array();
        $result = query(self::SQL_SELECT_FILE_MAP, array(':v' => $version_id));

        while ($row = $result->fetch()) {
            extract($row);
            $map[$path] = $hash;
        }
        return $map;
    }

    public static function checksumFolder($folder, $callback=null)
    {
        $result = array();

        if(!is_callable($callback)) {
            $callback = function($hash, $filename) use (&$result) {
                $result[] = array($hash, $filename);
                return array($hash, $filename);
            };
        }

        $diriterator = new RecursiveDirectoryIterator($folder);
        $objiterator = new RecursiveIteratorIterator(
            $diriterator,
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach($objiterator as $name => $object) {
            if (preg_match(self::CHECKSUM_IGNORE_PATTERN, $name)) {
                continue;
            }

            if ($object->getType() === 'file' && is_readable($name)) {
                $callback(md5_file($name), $name);
            }
        }
        return $result;
    }

    public function checksumLocalFolder($folder) {
        $current = getcwd();
        chdir($folder);
        $result = self::checksumFolder('.');
        chdir($current);
        return $result;
    }

    public function checksumRemoteFolder($folder) {
        $access = $this->getAccess();

        $result = $access->runPHP(__FILE__, array($folder));
        $result = trim($result);
        $result = empty($result) ? array() : explode("\n", $result);
        $result = array_map(function($line){
            return explode(':', $line);
        }, $result);

        return $result;
    }

    public function checksumInstance()
    {
        $webroot = $this->instance->webroot;

        if($this->instance === 'local') {
            $result = $this->checksumLocalFolder($webroot);
        } else {
            $result = $this->checksumRemoteFolder($webroot);
        }

        return $result;
    }

    public function checksumSource($version)
    {
        $app = $this->getApp();
        $folder = cache_folder($app, $version);
        $app->extractTo($version, $folder);
        $result = $this->checksumLocalFolder($folder);
        return $result;
    }

    public function addFile($version_id, $hash, $filename)
    {
        $args = array(
            ':version' => $version_id,
            ':path' => $filename,
            ':hash' => $hash
        );
        return query(self::SQL_INSERT_FILE, $args);
    }

    public function removeFile($version_id, $filename)
    {
        $args = array(':v' => $version_id, ':p' => $filename);
        return query(self::SQL_DELETE_FILE, $args);
    }

    public function replaceFile($version_id, $hash, $filename)
    {
        $this->removeFile($version_id, $filename);
        return $this->addFile($version_id, $hash, $filename);
    }

    public function validate($version_id)
    {

        $newFiles = array();
        $modifiedFiles = array();
        $missingFiles = array();

        $known = $this->getChecksums($version_id);
        $current = $this->checksumInstance();

        foreach ($current as $line) {
            list($hash, $filename) = $line;

            if (! isset($known[$filename]))
                $newFiles[$filename] = $hash;
            else {
                if ($known[$filename] != $hash)
                    $modifiedFiles[$filename] = $hash;

                unset($known[$filename]);
            }
        }

        foreach ($known as $filename => $hash)
            $missingFiles[$filename] = $hash;

        return array(
            'new' => $newFiles,
            'mod' => $modifiedFiles,
            'del' => $missingFiles,
        );
    }

    public function saveChecksums($version_id, $entries)
    {
        query('BEGIN TRANSACTION');

        foreach ($entries as $parts) {
            if (count($parts) != 2) continue;
            list($hash, $file) = $parts;
            $this->addFile($version_id, $hash, $file);
        }

        query('COMMIT');
    }
}


/**
 * If this is called directly, treat it as command
 */
if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    call_user_func(function () {
        $cur = getcwd();
        if (array_key_exists('REQUEST_METHOD', $_SERVER))
            $next = $_GET[1];

        elseif (count($_SERVER['argv']) > 1)
            $next = $_SERVER['argv'][1];

        if (isset($next) && file_exists($next))
            chdir($next);

        $callback = function($md5, $filename){ printf("%s:%s\n", $md5, $filename); };
        Audit_Checksum::checksumFolder('.', $callback);
        chdir($cur);
    });
}
