<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Manager\Update;

use Phar as PhpPhar;
use TikiManager\Config\Environment;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Manager\UpdateManager;
use TikiManager\Traits\FileArchive;
use TikiManager\Traits\FileDownload;

class Phar extends UpdateManager
{
    use FileArchive;
    use FileDownload;

    protected $phar;
    protected $pharPath;

    protected $updateUrl;

    public function __construct($targetFolder = null, $updateUrl = null)
    {
        $this->pharPath = $targetFolder ?? PhpPhar::running(false);
        parent::__construct($this->pharPath);

        $this->phar = 'phar://' . $this->pharPath;

        $this->updateUrl = $updateUrl ?? Environment::get(
            'UPDATE_PHAR_URL',
            'https://gitlab.com/tikiwiki/tiki-manager/-/jobs/artifacts/master/download?job=phar'
        );
    }

    /**
     * Download (phar/zip file) and extract the zip archive return the phar file
     *
     * @return bool|string The path to phar file, FALSE if operation failed.
     * @throws \Exception
     */
    public function downloadPhar()
    {
        $targetFile = Environment::get('TEMP_FOLDER') . '/tiki-manager.phar';
        $file = $this->download($this->updateUrl, $targetFile);

        if (mime_content_type($file) == 'application/octet-stream' &&
            $this->isValidPhar($file)) {
            return $file;
        }

        $fileZip = $file . '.zip';
        $filesystem = new Filesystem();
        $filesystem->rename($file, $fileZip, true);

        $extractedFolder = Environment::get('TEMP_FOLDER') . '/build';

        if (!$this->extract($fileZip, $extractedFolder)) {
            throw new \Exception('Error extracting files.');
        };

        $file = $extractedFolder . '/tiki-manager.phar';

        if ($filesystem->exists($file)) {
            $filesystem->rename($file, $targetFile);
        }

        $filesystem->remove($fileZip);
        $filesystem->remove($extractedFolder);

        return file_exists($targetFile) ? $targetFile : false;
    }

    /**
     * Replace existing phar with a new phar file
     *
     * @param string $new The path to the new phar file
     * @return bool The success of the replace
     */
    protected function replacePhar($new)
    {
        return copy($new, $this->pharPath);
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function update()
    {
        $filesystem = new Filesystem();

        try {
            if (!$file = $this->downloadPhar()) {
                throw new \Exception('Failed to retrieve phar file.');
            }

            if (!$this->replacePhar($file)) {
                throw new \Exception('Failed to update tiki-manager.phar with the new version');
            }
        } finally {
            $filesystem->remove($file);
        }
    }

    public function getType()
    {
        return 'Phar';
    }

    /**
     * @inheritDoc
     */
    public function getCurrentVersion()
    {
        $checksumFile = $this->phar . DIRECTORY_SEPARATOR . self::VERSION_FILENAME;
        return file_exists($checksumFile) ? CommandHelper::getVersionFileData($checksumFile) : false;
    }

    /**
     * @param $file
     * @return bool
     */
    protected function isValidPhar($file)
    {
        try {
            $phar = new PhpPhar($file);
            return isset($phar['tiki-manager.php']);
        } catch (\UnexpectedValueException $e) {
            return false;
        }
    }
}
