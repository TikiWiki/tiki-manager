<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\VersionControl;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Config\App;
use TikiManager\Application\Instance;

abstract class VersionControlSystem implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $command;
    protected $instance;
    protected $access;
    protected $repositoryUrl;
    protected $runLocally = false;
    protected $io;

    protected $vcsOptions;

    /**
     * VersionControlSystem constructor.
     * @param Instance $instance
     * @param array $options An array with settings on how the VCS should behaviour
     * @param LoggerInterface|null $logger
     * @throws \Exception
     */
    public function __construct(Instance $instance, array $vcsOptions = [], LoggerInterface $logger = null)
    {
        $this->instance = $instance;
        $this->access = $instance->getBestAccess('scripting');
        $this->vcsOptions = $vcsOptions;
        $this->io = App::get('io');

        $this->setLogger($logger ?? new NullLogger());
    }

    /**
     * Get current branch using the repository URL
     * @param $branch
     * @return string
     */
    public function getBranchUrl($branch)
    {
        return "{$this->repositoryUrl}/$branch";
    }

    /**
     * Check if a specific branch is an upgrade based on the current branch
     * @param $current
     * @param $branch
     * @return bool
     */
    public function isUpgrade($current, $branch)
    {
        $branch = $this->getBranchUrl($branch);
        return $current !== $branch;
    }

    /**
     * Get current VCS identifier based on the command used to execute it
     * @param $isCapitalized Should the identifier come capitalized?
     * @return string
     */
    public function getIdentifier($isCapitalized = false)
    {
        return $isCapitalized ? ucfirst($this->command) : strtoupper($this->command);
    }

    /**
     * Gets VCS for a given instance, if null returns default
     * @param Instance $instance
     * @return VersionControlSystem|null
     */
    public static function getVersionControlSystem(Instance $instance)
    {
        $type = $instance->vcs_type ?? $_ENV['DEFAULT_VCS'];
        $vcsInstance = null;

        switch (strtoupper($type)) {
            case 'SVN':
                $vcsInstance = new Svn($instance);
                break;
            case 'GIT':
                $vcsInstance = new Git($instance);
                break;
            case 'SRC':
                $vcsInstance = new Src($instance);
                break;
        }

        return $vcsInstance;
    }

    /**
     * runLocally variable setter
     * @param bool $runLocally
     */
    public function setRunLocally(bool $runLocally)
    {
        $this->runLocally = $runLocally;
    }

    /**
     * Get available branches within the repository
     * @return array
     */
    abstract public function getAvailableBranches();

    /**
     * Get current repository branch
     * @param $targetFolder
     * @return mixed
     */
    abstract public function getRepositoryBranch($targetFolder);

    /**
     * Main function to execute a command. Small part of logic will should be placed here.
     * This function was created to prevent redundancy.
     * @param $targetFolder
     * @param $toAppend
     * @return mixed
     */
    abstract public function exec($targetFolder, $toAppend);

    /**
     * Clones a specific branch within a repository
     * @param string $branchName
     * @param string $targetFolder
     * @return mixed
     */
    abstract public function clone($branchName, $targetFolder);

    /**
     * Reverts/discards changes previously made
     * @param $targetFolder
     * @return mixed
     */
    abstract public function revert($targetFolder);

    /**
     * Pulls recent changes from a repository
     * @param $targetFolder
     * @return mixed
     */
    abstract public function pull($targetFolder);

    /**
     * Clean and complete VCS related operations
     * @param $targetFolder
     * @return mixed
     */
    abstract public function cleanup($targetFolder);

    /**
     * Merges current modifications in a specific branch
     * @param $targetFolder
     * @param $branch
     * @return mixed
     */
    abstract public function merge($targetFolder, $branch);

    /**
     * Gets information related to the current branch
     * @param $targetFolder
     * @param $raw Should return in raw form
     * @return mixed
     */
    abstract public function info($targetFolder, $raw = false);

    /**
     * Get current revision from the current folder branch
     * @param $targetFolder
     * @return mixed
     */
    abstract public function getRevision($targetFolder);

    /**
     * Get date revision from the current revision
     * @param $targetFolder
     * @param $commitId
     * @return mixed
     */
    abstract public function getDateRevision($targetFolder, $commitId);

    /**
     * Checkout a branch given a branch name
     * @param $targetFolder
     * @param $branch
     * @return mixed
     */
    abstract public function checkoutBranch($targetFolder, $branch);

    /**
     * Upgrade an instance with a specific branch
     * @param $targetFolder
     * @param $branch
     * @return mixed
     */
    abstract public function upgrade($targetFolder, $branch);

    /**
     * Update current instance's branch
     * @param string $targetFolder
     * @param string $branch
     * @param int $lag
     * @void
     */
    abstract public function update(string $targetFolder, string $branch, int $lag = 0);

    abstract public function isFileVersioned($targetFolder, $fileName);

    /**
     * Check if the branch exists in remote server
     * @param $targetFolder
     * @param $branch
     * @return boolean
     */
    abstract public function hasRemote($targetFolder, $branch);

    /**
     * Return an array with all uncommited files
     * @param $targetFolder
     * @return mixed
     */
    abstract public function getChangedFiles($targetFolder);

    public function setIO(SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    public function setVCSOptions(array $options = [])
    {
        $this->vcsOptions = $options;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
