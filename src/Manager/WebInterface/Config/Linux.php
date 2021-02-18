<?php

namespace TikiManager\Manager\WebInterface\Config;

class Linux extends Generic
{
    public function isAvailable():bool
    {
        return PHP_OS == 'Linux';
    }

    public function getUserWebRoot($webRoot):string
    {
        if (function_exists('posix_getpwuid') && $userInfo = posix_getpwuid(fileowner($webRoot))) {
            return $userInfo['name'];
        }

        return parent::getUserWebRoot();
    }

    public function getGroupWebRoot($webRoot):string
    {
        if (function_exists('posix_getgrgid') && $groupInfo = posix_getgrgid(filegroup($webRoot))) {
            return $groupInfo['name'];
        }

        return parent::getGroupWebRoot();
    }
}
