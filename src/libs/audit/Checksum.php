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
        $result = query(SQL_SELECT_FILE_MAP, array(':v' => $version_id));

        while ($row = $result->fetch()) {
            extract($row);
            $map[$path] = $hash;
        }
        return $map;
    }

    public function checksumFolder($folder) {
        $access = $this->getAccess();

        return $access->runPHP(
            TRIM_ROOT . '/scripts/generate_md5_list.php',
            array($folder)
        );
    }

    public function checksumInstance()
    {
        $webroot = $this->instance->webroot;
        $result = $this->checksumFolder($webroot);
        return $result;
    }

    public function checksumSource($version)
    {
        $app = $this->getApp();
        $folder = cache_folder($app, $version);
        $app->extractTo($version, $folder);

        ob_start();
        include TRIM_ROOT . '/scripts/generate_md5_list.php';
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
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
        return query(SQL_DELETE_FILE, $args);
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

        foreach (explode("\n", $current) as $line) {
            list($hash, $filename) = explode(':', $line);

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

    public function saveChecksums($version_id, $input)
    {
        query('BEGIN TRANSACTION');

        $entries = explode("\n", $input);
        foreach ($entries as $line) {
            $parts = explode(':', $line);
            if (count($parts) != 2) continue;

            list($hash, $file) = $parts;
            $this->addFile($version_id, $hash, $file);
        }

        query('COMMIT');
    }
}
