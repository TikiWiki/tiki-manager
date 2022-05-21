<?php

namespace TikiManager\Manager\WebInterface\Config;

use TikiManager\Manager\WebInterface\Config;

class MacOS extends Config
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function getExampleDomainDirectory(): string
    {
        return '/Applications/MAMP/htdocs/webtikimanager.example.com';
    }

    public function getExampleDataDirectory(): string
    {
        return '/Applications/MAMP/htdocs/webtikimanager.example.com';
    }

    public function getExampleURL(): string
    {
        return 'http://webtikimanager.example.com';
    }

    public function getExamplePermissionDirectory(): string
    {
        return $this->getExampleDomainDirectory();
    }

    public function getUserWebRoot($webRoot): string
    {
        return getenv('WWW_USER');
    }

    public function getGroupWebRoot($webRoot): string
    {
        return getenv('WWW_GROUP');
    }
}
