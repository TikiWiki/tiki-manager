<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Manager\Update;

use Exception;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\Environment;
use TikiManager\Manager\UpdateManager;
use TikiManager\Traits\FileArchive;
use TikiManager\Traits\FileDownload;

class Src extends UpdateManager
{
    use FileArchive;
    use FileDownload;

    protected $downloadUrl;

    public function __construct($targetFolder)
    {
        parent::__construct($targetFolder);

        $this->downloadUrl = Environment::get(
            'DOWNLOAD_ARCHIVE_URL',
            'https://gitlab.com/tikiwiki/tiki-manager/-/archive/master/tiki-manager-master.zip'
        );
    }

    /**
     * @return bool|false|string
     * @throws Exception
     */
    public function downloadSrc()
    {
        $zipFile = Environment::get('TEMP_FOLDER') . '/tiki-manager-master.zip';
        return  $this->download($this->downloadUrl, $zipFile);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function update()
    {
        if (!$zip = $this->downloadSrc()) {
            throw new \Exception('Failed to retrieve archive file from ' . $this->downloadUrl);
        }

        $unZippedFolder = Environment::get('TEMP_FOLDER') . '/tiki-manager-master';

        if (!$this->extract($zip, $unZippedFolder) || !file_exists($unZippedFolder . '/tiki-manager')) {
            throw new \Exception('Error extracting files.');
        }

        $filesystem = new Filesystem();
        $filesystem->remove($zip);
        $filesystem->mirror($unZippedFolder, $this->targetFolder);
        $filesystem->remove($unZippedFolder);

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
        return file_exists($checksumFile) ? CommandHelper::getVersionFileData($checksumFile) : false;
    }

    protected function setCurrentVersion($versionInfo)
    {
        $checksumFile = trim($this->targetFolder . DIRECTORY_SEPARATOR . self::VERSION_FILENAME);

        $comments = '';
        $jsonEncoded = '';

        $onlyComments = CommandHelper::getVersionFileData($checksumFile, true);
        if (! empty($onlyComments)) {
            $comments = implode("\n", $onlyComments) . "\n";
        }

        if (! empty($versionInfo)) {
            $jsonEncoded = json_encode($versionInfo);
        }

        $finalVersionContent = $comments . $jsonEncoded;

        return file_put_contents($checksumFile, $finalVersionContent);
    }
}
