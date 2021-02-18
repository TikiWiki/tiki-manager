<?php
namespace TikiManager\Manager\WebInterface;

class VirtualminConfig extends Config
{

    public function isAvailable()
    {
        return file_exists('/usr/sbin/virtualmin');
    }

    public function getExampleDomainDirectory()
    {
        return '/home/example/public_html';
    }

    public function getExamplePermissionDirectory()
    {
        return '/home/example/public_html';
    }

    public function getExampleDataDirectory()
    {
        return '/home/example/public_html';
    }

    public function getExampleURL()
    {
        return 'http://webtikimanager.example.com ';
    }

    public function getUserWebRoot($webRoot)
    {
        if (function_exists('posix_getpwuid') && $userInfo = posix_getpwuid(fileowner($webRoot))) {
            return $userInfo['name'];
        }
        return false;
    }

    public function getGroupWebRoot($webRoot)
    {
        if (function_exists('posix_getgrgid') && $groupInfo = posix_getgrgid(filegroup($webRoot))) {
            return $groupInfo['name'];
        }
        return false;
    }
}
