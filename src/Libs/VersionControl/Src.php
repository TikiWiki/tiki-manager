<?php

namespace TikiManager\Libs\VersionControl;

use TikiManager\Application\Restore;
use TikiManager\Application\Version;
use TikiManager\Config\Environment;
use TikiManager\Traits\FileArchive;
use TikiManager\Application\Exception\VcsException;

class Src extends VersionControlSystem
{
    use FileArchive;

    public static $pattern = '/tiki-(.*)\.(tar\.bz2|zip|7z|tar\.gz)/';

    protected $command = 'src';

    /**
     * Get available branches within the repository
     * @return array
     */
    public function getAvailableBranches()
    {
        $versions = $this->getAvailableVersions();
        return array_map(function ($v) {
            return Version::buildFake('src', trim($v));
        }, $versions);
    }

    /**
     * Get current repository branch
     * @param $targetFolder
     * @return mixed
     */
    public function getRepositoryBranch($targetFolder)
    {
        $file = 'lib/setup/twversion.class.php';
        $contents = $this->access->fileGetContents($file);

        preg_match('/.*\$this->version\s*=\s*\'([^\']*)\';.*/s', $contents, $matches);

        return $matches[1];
    }

    /**
     * Main function to execute a command. Small part of logic will should be placed here.
     * This function was created to prevent redundancy.
     * @param $targetFolder
     * @param $toAppend
     * @return mixed
     */
    public function exec($targetFolder, $toAppend)
    {
        // Left blank on purpose;
        return;
    }

    /**
     * Clones a specific branch within a repository
     * @param string $branchName
     * @param string $targetFolder
     * @return void|false
     */
    public function clone($branchName, $targetFolder)
    {
        $files = $this->findFileForBranch($branchName);
        if (empty($files)) {
            return false;
        }
        //extract file
        $this->extract(Environment::get('TRIM_SRC_FOLDER') . DIRECTORY_SEPARATOR . $files[0], $targetFolder);
    }

    /**
     * Reverts/discards changes previously made
     * @param $targetFolder
     * @return mixed
     */
    public function revert($targetFolder)
    {
        // Left blank on purpose;
        return;
    }

    /**
     * Pulls recent changes from a repository
     * @param $targetFolder
     * @return mixed
     */
    public function pull($targetFolder)
    {
        // Left blank on purpose;
        return;
    }

    /**
     * Clean and complete VCS related operations
     * @param $targetFolder
     * @return mixed
     */
    public function cleanup($targetFolder)
    {
        // Left blank on purpose;
        return;
    }

    /**
     * Merges current modifications in a specific branch
     * @param $targetFolder
     * @param $branch
     * @return mixed
     */
    public function merge($targetFolder, $branch)
    {
        // Left blank on purpose;
        return;
    }

    /**
     * Gets information related to the current branch
     * @param $targetFolder
     * @param $raw Should return in raw form
     * @return mixed
     */
    public function info($targetFolder, $raw = false)
    {
        // Left blank on purpose;
        return;
    }

    /**
     * Get current revision from the current folder branch
     * @param $targetFolder
     * @return mixed
     */
    public function getRevision($targetFolder)
    {
        // Left blank on purpose;
        return;
    }

    /**
     * Get date revision from the current revision
     * @param $targetFolder
     * @param $commitId
     * @return mixed|void
     */
    public function getDateRevision($targetFolder, $commitId)
    {
        // Left blank on purpose;
        return;
    }

    /**
     * Checkout a branch given a branch name
     * @param $targetFolder
     * @param $branch
     * @return mixed
     */
    public function checkoutBranch($targetFolder, $branch)
    {
        // Left blank on purpose;
        return;
    }

    /**
     * Upgrade an instance with a specific branch
     * @param $targetFolder
     * @param $branch
     * @return mixed
     */
    public function upgrade($targetFolder, $branch)
    {
        echo $targetFolder;
        // Left blank on purpose;
        return;
    }

    /**
     * Update current instance's branch
     * @param string $targetFolder
     * @param string $branch
     * @param int $lag
     * @return null
     */
    public function update(string $targetFolder, string $branch, int $lag = 0)
    {
        if (preg_match('/(\d+)\.(\d+).*/', $branch, $matches1)) {
            $version = $matches1[0];
            $cacheFolder = $_ENV['CACHE_FOLDER'] . DIRECTORY_SEPARATOR . 'tiki-src-' . $version;
            if (file_exists($cacheFolder)) {
                $this->deleteFolder($cacheFolder);
            }
            $this->clone($version, $cacheFolder);

            $restore = new Restore($this->instance);
            $restore->restoreFolder($cacheFolder, $targetFolder);
        }
        //Find for compatible and superior versions
    }

    public function getBranchToUpdate($branch)
    {
        if (preg_match('/(\d+)\.(\d+).*/', $branch, $matches1)) {
            $major = $matches1[1];
            $toUpdate = $matches1[0];
            $versions = $this->getAvailableVersions();
            foreach ($versions as $v) {
                if (preg_match('/(\d+)\.(\d+).*/', $v, $matches2) && $major == $matches2[1]) {
                    if (version_compare($v, $toUpdate, '>')) {
                        $toUpdate = $v;
                    }
                }
            }
            return $toUpdate;
        }
        return null;
    }

    private function getAvailableVersions()
    {
        $versions = array_values(array_filter(array_map(function ($file) {
            preg_match(Src::$pattern, $file, $matches);
            return (count($matches) == 3) ? trim($matches[1]) : null;
        }, scandir($_ENV['TRIM_SRC_FOLDER']))));
        sort($versions, SORT_NATURAL);
        return $versions;
    }

    /**
     * @param $branchName
     * @return array
     */
    public function findFileForBranch($branchName): array
    {
        //find file for branch name
        return array_values(array_filter(scandir($_ENV['TRIM_SRC_FOLDER']), function ($file) use ($branchName) {
            preg_match(Src::$pattern, $file, $matches);
            return (count($matches) == 3) ? trim($matches[1]) == $branchName : false;
        }));
    }

    private function deleteFolder($dir)
    {
        `rm -rf $dir`;
    }

    public function isFileVersioned($targetFolder, $fileName)
    {
        return false;
    }

    public function getChangedFiles($targetFolder)
    {
        // This method is not intended to be used
        // Used in partial backups (only supported on Git)
        return false;
    }

    public function getUntrackedFiles()
    {
        return [];
    }

    public function hasRemote($targetFolder, $branch)
    {
        // This method is not intended to be used
        // Used in partial backups (only supported on Git)
        return false;
    }

    public function isRevisionPresent($targetFolder, $revision)
    {
        throw new VcsException('This method is not supported for SRC');
    }

    public function deepenCloneUntilRevisionPresent($targetFolder, $revision)
    {
        throw new VcsException('This method is not supported for SRC');
    }

    public function isBranchBelongsToRepo($branch, $repoUrl)
    {
        throw new VcsException('This method is not supported for SRC');
    }

    /**
     * Bisect operation is not allowed for SRC.
     * It is for git only
     * @throws VcsException
     */
    public function startBisect($targetFolder, $badCommit, $goodCommit)
    {
        throw new VcsException('SRC does not support bisect operations.');
    }

    /**
     * Bisect operation is not allowed for SRC.
     * It is for git only
     * @throws VcsException
     */
    public function markGoodBisect($targetFolder, $commitId)
    {
        throw new VcsException('SRC does not support bisect operations.');
    }

    /**
     * Bisect operation is not allowed for SRC.
     * It is for git only
     * @throws VcsException
     */
    public function markBadBisect($targetFolder, $commitId)
    {
        throw new VcsException('SRC does not support bisect operations.');
    }

    /**
     * Bisect operation is not allowed for SRC.
     * It is for git only
     * @throws VcsException
     */
    public function resetBisect($targetFolder)
    {
        throw new VcsException('SRC does not support bisect operations.');
    }
}
