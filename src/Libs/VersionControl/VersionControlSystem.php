<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\VersionControl;

use TikiManager\Application\Instance;

abstract class VersionControlSystem
{
    protected $command;
    protected $access;
    protected $repositoryUrl;
    protected $runLocally = false;

    /**
     * VersionControlSystem constructor.
     * @param $access
     */
    public function __construct($access)
    {
        $this->access = $access;
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
        $is_upgrade = $current !== $branch;

        return $is_upgrade;
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
     * Gets default VCS
     * @param Instance $instance
     * @return string
     */
    public static function getDefaultVersionControlSystem(Instance $instance)
    {
        $type = $_ENV['DEFAULT_VCS'];
        $access = $instance->getBestAccess('scripting');
        $vcsInstance = null;

        switch (strtoupper($type)) {
            case 'SVN':
                $vcsInstance = new Svn($access);
                break;
            case 'GIT':
                $vcsInstance = new Git($access);
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
    abstract protected function getRepositoryBranch($targetFolder);

    /**
     * Main function to execute a command. Small part of logic will should be placed here.
     * This function was created to prevent redundancy.
     * @param $targetFolder
     * @param $toAppend
     * @param $forcePathOnCommand
     * @return mixed
     */
    abstract public function exec($targetFolder, $toAppend, $forcePathOnCommand = false);

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
     * @param $targetFolder
     * @return mixed
     */
    abstract public function update($targetFolder, $branch);
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
