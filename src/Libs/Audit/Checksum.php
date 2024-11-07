<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Libs\Audit;

class Checksum
{
    const SQL_SELECT_FILE_MAP = <<<SQL
        SELECT
            path, hash
        FROM
            file
        WHERE
            version_id = :v
            ;
SQL;

    const SQL_SELECT_FILE_COUNT_BY_VERSION = <<<SQL
        SELECT
            COUNT(*)
        FROM
            file
        WHERE
            version_id = :id
        ;
SQL;

    const SQL_INSERT_FILE = <<<SQL
        INSERT INTO
            file
            (version_id, path, hash)
        VALUES
            (:version, :path, :hash)
        ;
SQL;

    const SQL_INSERT_FILE_REPLICATE = <<<SQL
        INSERT INTO
            file
            (version_id, path, hash)
            SELECT
                :new, path, hash
            FROM
                file
            WHERE
                version_id = :old
        ;
SQL;

    const SQL_DELETE_FILE = <<<SQL
        DELETE FROM
            file
        WHERE
            path = :p and version_id = :v
        ;
SQL;

    const CHECKSUM_IGNORE_PATTERN = '#(^\./temp/|^\./modules/cache/mod|^\./templates_c/|^\./maintenance\.php|/\.git/|/\.svn/)#';

    public static function hasChecksums($version_id)
    {
        $args = [':id' => $version_id];
        $result = query(self::SQL_SELECT_FILE_COUNT_BY_VERSION, $args);
        return ($result->fetchColumn() > 0);
    }

    public static function getChecksums($version_id)
    {
        $map = [];
        $result = query(self::SQL_SELECT_FILE_MAP, [':v' => $version_id]);

        while ($row = $result->fetch()) {
            extract($row);
            $map[$path] = $hash;
        }
        return $map;
    }

    public static function checksumFolder($folder, $callback = null)
    {
        $result = [];

        if (!is_callable($callback)) {
            $callback = function ($hash, $filename) use (&$result) {
                $result[] = [$hash, $filename];
                return [$hash, $filename];
            };
        }

        $diriterator = new \RecursiveDirectoryIterator($folder);
        $objiterator = new \RecursiveIteratorIterator(
            $diriterator,
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($objiterator as $name => $object) {
            if (preg_match(self::CHECKSUM_IGNORE_PATTERN, $name)) {
                continue;
            }

            if ($object->getType() === 'file' && is_readable($name)) {
                $callback(md5_file($name), $name);
            }
        }
        return $result;
    }

    public static function checksumLocalFolder($folder)
    {
        $current = getcwd();
        chdir($folder);
        $result = self::checksumFolder('.');
        chdir($current);
        return $result;
    }

    public static function checksumRemoteFolder($folder, $access)
    {
        $result = $access->runPHP(__FILE__, [$folder]);
        $result = trim($result);
        $result = empty($result) ? [] : explode("\n", $result);
        $result = array_map(function ($line) {
            return explode(':', $line);
        }, $result);

        return $result;
    }

    public static function checksumSource($version, $app)
    {
        $folder = cache_folder($app, $version);
        $app->extractTo($version, $folder);
        $result = self::checksumLocalFolder($folder);
        return $result;
    }

    public static function addFile($version_id, $hash, $filename)
    {
        $args = [
            ':version' => $version_id,
            ':path' => $filename,
            ':hash' => $hash
        ];
        return query(self::SQL_INSERT_FILE, $args);
    }

    public static function addFiles($version_id, $hashFiles = [])
    {
        query('BEGIN TRANSACTION');
        foreach ($hashFiles as $hashFile) {
            list($hash, $filename) = $hashFile;
            self::addFile($version_id, $hash, $filename);
        }
        return query('COMMIT');
    }

    public static function removeFile($version_id, $filename)
    {
        $args = [':v' => $version_id, ':p' => $filename];
        return query(self::SQL_DELETE_FILE, $args);
    }

    public static function replaceFile($version_id, $hash, $filename)
    {
        self::removeFile($version_id, $filename);
        return self::addFile($version_id, $hash, $filename);
    }

    public static function replaceFiles($version_id, $hashFiles)
    {
        query('BEGIN TRANSACTION');
        foreach ($hashFiles as $hashFile) {
            list($hash, $filename) = $hashFile;
            self::replaceFile($version_id, $hash, $filename);
        }
        return query('COMMIT');
    }

    public static function validate($version_id, $current_checksums = [])
    {

        $newFiles = [];
        $modifiedFiles = [];
        $missingFiles = [];
        $pristineFiles = [];

        $known = self::getChecksums($version_id);

        foreach ($current_checksums as $line) {
            list($hash, $filename) = $line;

            if (! isset($known[$filename])) {
                $newFiles[$filename] = $hash;
            } else {
                if ($known[$filename] != $hash) {
                    $modifiedFiles[$filename] = $hash;
                } else {
                    $pristineFiles[$filename] = $hash;
                }
                unset($known[$filename]);
            }
        }

        foreach ($known as $filename => $hash) {
            $missingFiles[$filename] = $hash;
        }

        return [
            'new' => $newFiles,
            'mod' => $modifiedFiles,
            'del' => $missingFiles,
            'pri' => $pristineFiles,
        ];
    }

    public static function saveChecksums($version_id, $entries)
    {
        query('BEGIN TRANSACTION');

        foreach ($entries as $parts) {
            if (count($parts) != 2) {
                continue;
            }
            list($hash, $file) = $parts;
            self::addFile($version_id, $hash, $file);
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
        if (array_key_exists('REQUEST_METHOD', $_SERVER)) {
            $next = $_GET[1];
        } elseif (count($_SERVER['argv']) > 1) {
            $next = $_SERVER['argv'][1];
        }

        if (isset($next) && file_exists($next)) {
            chdir($next);
        }

        $callback = function ($md5, $filename) {
            printf("%s:%s\n", $md5, $filename);
        };
        Checksum::checksumFolder('.', $callback);
        chdir($cur);
    });
}
