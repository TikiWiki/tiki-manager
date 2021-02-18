<?php

namespace TikiManager\Manager\WebInterface\Config;

class ClearOS extends Linux
{

    public function isAvailable(): bool
    {
        return file_exists('/etc/clearos-release');
    }

    public function getExampleDomainDirectory(): string
    {
        return '/var/www/virtual/webtikimanager.example.com';
    }

    public function getExampleDataDirectory(): string
    {
        return '/var/www/virtual/webtikimanager.example.com/html';
    }

    public function getExampleURL(): string
    {
        return 'http://webtikimanager.example.com';
    }

    public function getExamplePermissionDirectory(): string
    {
        return $this->getExampleDomainDirectory();
    }
}
