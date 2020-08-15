<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Libs\Helpers;

use TikiManager\Application\Instance;

class ApplicationHelper
{
    /**
     * @param $array
     * @param bool $objectFlat
     * @return array
     */
    public static function arrayFlatten($array, $objectFlat = false)
    {
        $result = [];
        $visited = [];
        $queue = [];
        $current = null;

        if (is_array($array) || ($objectFlat && is_object($array))) {
            $queue[] = $array;
        }

        while (!empty($queue)) {
            $current = array_shift($queue);
            if ($objectFlat && is_object($current)) {
                $current = get_object_vars($current);
            }
            if (!is_array($current)) {
                $result[] = $current;
                continue;
            }
            foreach ($current as $key => $value) {
                if (is_array($value) || ($objectFlat && is_object($value))) {
                    $queue[] = $value;
                } else {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * @author http://php.net/manual/pt_BR/function.realpath.php#84012
     */
    public static function getAbsolutePath($path)
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $parts = array_filter($parts, 'strlen');

        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        if (substr($path, 0, 1) === DIRECTORY_SEPARATOR) {
            $path = DIRECTORY_SEPARATOR;
        } else {
            $path = '';
        }

        return $path . implode(DIRECTORY_SEPARATOR, $absolutes);
    }

    /**
     * Check if application is running on Windows
     *
     * @return bool
     */
    public static function isWindows()
    {
        return substr(PHP_OS, 0, 3) == 'WIN';
    }

    public static function getInstanceTypes()
    {
        $instanceTypes = ApplicationHelper::isWindows() ? 'local' : Instance::TYPES;
        $listInstanceTypes = explode(',', $instanceTypes);
        return $listInstanceTypes;
    }
}
