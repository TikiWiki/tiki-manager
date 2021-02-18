<?php
namespace TikiManager\Manager\WebInterface\Config;

class Virtualmin extends Linux
{
    public function isAvailable():bool
    {
        return file_exists('/usr/sbin/virtualmin');
    }

    public function getExampleDomainDirectory():string
    {
        return '/home/example/public_html';
    }

    public function getExamplePermissionDirectory():string
    {
        return '/home/example/public_html';
    }

    public function getExampleDataDirectory():string
    {
        return '/home/example/public_html';
    }
}
