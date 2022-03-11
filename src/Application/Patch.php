<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application;

class Patch
{
    const SQL_INSERT_PATCH = <<<SQL
        INSERT OR REPLACE INTO
            patch
            (patch_id, instance_id, package, url)
        VALUES
            (:id, :instance, :package, :url)
        ;
SQL;

    const SQL_DELETE_PATCH = <<<SQL
        DELETE FROM
            patch
        WHERE patch_id = :id
        ;
SQL;

    const SQL_SELECT_PATCHES_FOR_INSTANCE = <<<SQL
        SELECT
            patch_id id, instance_id instance, package, url
        FROM
            patch
        WHERE
            instance_id = :id
        ORDER BY
            patch_id
        ;
SQL;

    const SQL_SELECT_PATCH = <<<SQL
        SELECT
            patch_id id, instance_id instance, package, url
        FROM
            patch
        WHERE
            patch_id = :id
        ;
SQL;

    public $id;
    public $instance;
    public $package;
    public $url;

    public static function initialize($instanceId, $package, $url)
    {
        $patch = new Patch;
        $patch->instance = $instanceId;
        $patch->package = $package;
        $patch->url = $url;
        return $patch;
    }

    public static function getPatches($instanceId)
    {
        $result = query(self::SQL_SELECT_PATCHES_FOR_INSTANCE, [':id' => $instanceId]);

        $patches = [];
        while ($patch = $result->fetchObject('TikiManager\Application\Patch')) {
            $patches[] = $patch;
        }

        return $patches;
    }

    public static function find($patchId)
    {
        $result = query(self::SQL_SELECT_PATCH, [':id' => $patchId]);
        return $result->fetchObject('TikiManager\Application\Patch');
    }

    public function exists()
    {
        $patches = Patch::getPatches($this->instance);
        foreach ($patches as $patch) {
            if ($patch->package == $this->package && $patch->url == $this->url) {
                return true;
            }
        }
        return false;
    }

    public function save()
    {
        $params = [
            ':id' => $this->id,
            ':instance' => $this->instance,
            ':package' => $this->package,
            ':url' => $this->url,
        ];

        query(self::SQL_INSERT_PATCH, $params);

        $rowid = rowid();

        if (! $this->id && $rowid) {
            $this->id = $rowid;
        }
    }

    public function delete()
    {
        query(self::SQL_DELETE_PATCH, [':id' => $this->id]);
    }
}
