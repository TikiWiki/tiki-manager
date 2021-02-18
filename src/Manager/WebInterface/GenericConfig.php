<?php

namespace TikiManager\Manager\WebInterface;

class GenericConfig extends Config
{

    public function isAvailable()
    {
        return true;
    }

    public function getExampleDomainDirectory()
    {
        return '/var/www/virtual/webtikimanager.example.com';
    }

    public function getExampleDataDirectory()
    {
        return '/var/www/virtual/webtikimanager.example.com/html';
    }

    public function getExampleURL()
    {
        return 'http://webtikimanager.example.com';
    }

    public function getExamplePermissionDirectory()
    {
        return dirname($this->getExampleDomainDirectory());
    }

    public function getUserWebRoot($webRoot)
    {
        return getenv('WWW_USER');
    }

    public function getGroupWebRoot($webRoot)
    {
        return getenv('WWW_GROUP');
    }
}
