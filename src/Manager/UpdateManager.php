<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Manager;

use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;
use TikiManager\Config\Environment;
use TikiManager\Manager\Update\Git;
use TikiManager\Manager\Update\Phar;
use TikiManager\Manager\Update\Src;
use Phar as PhpPhar;

abstract class UpdateManager
{
    protected $targetFolder;

    public const VERSION_FILENAME = '.version';
    public const VERSION_UPDATE_FILENAME = '.updateVersion';

    /**
     * VersionControlSystem constructor.
     * @param $targetFolder
     */
    public function __construct($targetFolder)
    {
        $this->targetFolder = $targetFolder;
    }

    /**
     * Gets Tiki Manager updater
     * @param null $targetFolder
     * @return UpdateManager
     */
    public static function getUpdater($targetFolder = null)
    {
        if (PhpPhar::running(false) ||
            (is_string($targetFolder) && substr($targetFolder, -4) == 'phar')) {
            return new Phar();
        }

        $basePath = $targetFolder ?? Environment::get('TRIM_ROOT', dirname(__FILE__, 3));
        $gitPath = $basePath . DIRECTORY_SEPARATOR . '.git';

        return is_dir($gitPath) ? new Git($basePath) : new Src($basePath);
    }

    protected function getApiBaseUrl()
    {
        $baseUrl = Environment::get('GITLAB_URL', 'https://gitlab.com');
        $projectId = Environment::get('GITLAB_PROJECT_ID', '9558938');

        return sprintf('%s/api/v4/projects/%s', $baseUrl, $projectId);
    }

    public function hasVersion()
    {
        return boolval($this->getCurrentVersion());
    }

    public function hasUpdateAvailable($checkRemote)
    {
        $currentVersion = $this->getCurrentVersion();

        if (!$checkRemote) {
            $updateVersion = $this->getCacheVersionInfo();
        } else {
            $updateVersion = $this->getRemoteVersion();
            $this->setCacheVersionInfo($updateVersion);
        }

        if (empty($currentVersion) || empty($updateVersion)) {
            return false;
        }

        $versionDiff = $currentVersion['version'] !== $updateVersion['version'];
        $timeDiff = strtotime($currentVersion['date']) < strtotime($updateVersion['date']);

        return $versionDiff && $timeDiff;
    }

    /**
     * Update tiki manager
     */
    abstract public function update();


    /**
     * return the local hash version
     * @return array|false versionStatus
     */
    abstract public function getCurrentVersion();


    /**
     * return the local hash version
     * @param null $branch
     * @return array|false versionStatus
     */
    public function getRemoteVersion($branch = null)
    {
        $client = HttpClient::create();
        $query = [
            'first_parent' => true,
            'per_page' => 1,
        ];
        if ($branch) {
            $query['ref_name'] = $branch;
        }
        $baseUrl = $this->getApiBaseUrl();
        $response = $client->request('GET', $baseUrl . '/repository/commits?', ['query' => $query]);
        if ($response->getStatusCode() == 200 && !empty($content = $response->toArray())) {
            return [
                'version' => $content[0]['short_id'],
                'date' => $content[0]['created_at']
            ];
        }
        return false;
    }

    /**
     * @return string
     */
    public function info()
    {
        $version = $this->getCurrentVersion();

        return sprintf(
            "%s detected\nVersion: %s\nDate: %s",
            $this->getType(),
            $version['version'],
            date(\DateTime::COOKIE, strtotime($version['date']))
        );
    }

    abstract public function getType();

    protected function runComposerInstall()
    {
        $composerPath = Environment::get('COMPOSER_PATH');
        $composer = $composerPath == 'composer' ? $composerPath : 'php ' . $composerPath;

        $process = new Process([$composer,
            'install',
            '-d',
            $this->targetFolder,
            '--no-interaction',
            '--prefer-source'
        ]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Unable to install composer dependencies. Please run composer install manually');
        }
    }

    public function getCacheVersionInfo()
    {
        $file = Environment::get('CACHE_FOLDER') . DIRECTORY_SEPARATOR . static::VERSION_UPDATE_FILENAME;

        return file_exists($file) ? json_decode(file_get_contents($file), true) : false;
    }

    public function setCacheVersionInfo($info)
    {
        $file = Environment::get('CACHE_FOLDER') . DIRECTORY_SEPARATOR . static::VERSION_UPDATE_FILENAME;
        file_put_contents($file, json_encode($info));
    }
}
