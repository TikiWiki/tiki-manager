<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Manager\Update;

use Exception;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Config\Environment;
use TikiManager\Libs\Helpers\File;
use TikiManager\Manager\UpdateManager;

class Src extends UpdateManager
{
    protected $downloadUrl;

    public function __construct($targetFolder)
    {
        parent::__construct($targetFolder);

        $this->downloadUrl = Environment::get(
            'DOWNLOAD_ARCHIVE_URL',
            'https://gitlab.com/tikiwiki/tiki-manager/-/archive/master/tiki-manager-master.zip'
        );
    }

    public function downloadSrc()
    {
        $zipFile = Environment::get('TEMP_FOLDER') . '/tiki-manager-master.zip';
        return  File::download($this->downloadUrl, $zipFile);
    }

    protected function extractZip($file)
    {
        $unZippedFolder = Environment::get('TEMP_FOLDER') . '/tiki-manager-master';

        if (!File::unarchive($file, $unZippedFolder) || !file_exists($unZippedFolder . '/tiki-manager')) {
            throw new \Exception('Error extracting files.');
        }

        return $unZippedFolder;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function update()
    {
        $fs = new Filesystem();

        if (!$zip = $this->downloadSrc()) {
            throw new \Exception('Failed to retrieve archive file from ' . $this->downloadUrl);
        }

        $extractedPath = $this->extractZip($zip);
        $fs->remove($zip);

        $fs->mirror($extractedPath, $this->targetFolder);
        $fs->remove($extractedPath);

        $this->setCurrentVersion($this->getRemoteVersion());
        $this->runComposerInstall();
    }

    public function getType()
    {
        return 'Source Code';
    }

    public function getCurrentVersion()
    {
        $checksumFile = $this->targetFolder . DIRECTORY_SEPARATOR . self::VERSION_FILENAME;
        return file_exists($checksumFile) ? json_decode(file_get_contents($checksumFile), true) : false;
    }

    protected function setCurrentVersion($versionInfo)
    {
        $checksumFile = $this->targetFolder . DIRECTORY_SEPARATOR . self::VERSION_FILENAME;
        return file_put_contents($checksumFile, json_encode($versionInfo));
    }
}
