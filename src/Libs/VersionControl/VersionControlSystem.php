<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\VersionControl;

use TikiManager\Libs\Helpers\Configuration;
use TikiManager\Application\Instance;

abstract class VersionControlSystem
{
    protected $command;
    protected $access;
    protected $repository_url;

    /**
     * VersionControlSystem constructor.
     * @param $access
     */
    function __construct($access)
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
        return "{$this->repository_url}/$branch";
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
     * @param $is_capitalized Should the identifier come capitalized?
     * @return string
     */
    public function getIdentifier($is_capitalized = false)
    {
        return $is_capitalized ? ucfirst($this->command) : strtoupper($this->command);
    }

    /**
     * Gets default VCS based on the config.yml file
     * @return string
     */
    public static function getDefaultVersionControlSystem(Instance $instance)
    {
        $type = DEFAULT_VERSION_CONTROL_SYSTEM;
        $access = $instance->getBestAccess('scripting');
        $configuration_instance = new Configuration();
        $config = $configuration_instance->get();
        $vcs_instance = null;

        if (! empty($config['instance']['default_version_control_system'])) {
            $type = $config['instance']['default_version_control_system'];
        }

        switch (strtoupper($type)) {
            case 'SVN':
                $vcs_instance = new Svn($access);
                break;
            case 'GIT':
                $vcs_instance = new Git($access);
                break;
        }

        return $vcs_instance;
    }

    /**
     * Get available branches within the repository
     * @return array
     */
    abstract public function getAvailableBranches();

    /**
     * Get current repository branch
     * @param $target_folder
     * @return mixed
     */
    abstract protected function getRepositoryBranch($target_folder);

    /**
     * Main function to execute a command. Small part of logic will should be placed here.
     * This function was created to prevent redundancy.
     * @param $target_folder
     * @param $to_append
     * @param $force_path_on_command
     * @return mixed
     */
    abstract public function exec($target_folder, $to_append, $force_path_on_command = false);

    /**
     * Clones a specific branch within a repository
     * @param string $branch_name
     * @param string $target_folder
     * @return mixed
     */
    abstract public function clone($branch_name, $target_folder);

    /**
     * Reverts/discards changes previously made
     * @param $target_folder
     * @return mixed
     */
    abstract public function revert($target_folder);

    /**
     * Pulls recent changes from a repository
     * @param $target_folder
     * @return mixed
     */
    abstract public function pull($target_folder);

    /**
     * Clean and complete VCS related operations
     * @param $target_folder
     * @return mixed
     */
    abstract public function cleanup($target_folder);

    /**
     * Merges current modifications in a specific branch
     * @param $target_folder
     * @param $branch
     * @return mixed
     */
    abstract public function merge($target_folder, $branch);

    /**
     * Gets information related to the current branch
     * @param $target_folder
     * @param $raw Should return in raw form
     * @return mixed
     */
    abstract public function info($target_folder, $raw = false);

    /**
     * Get current revision from the current folder branch
     * @param $target_folder
     * @return mixed
     */
    abstract public function getRevision($target_folder);

    /**
     * Checkout a branch given a branch name
     * @param $target_folder
     * @param $branch
     * @return mixed
     */
    abstract public function checkoutBranch($target_folder, $branch);

    /**
     * Upgrade an instance with a specific branch
     * @param $target_folder
     * @param $branch
     * @return mixed
     */
    abstract public function upgrade($target_folder, $branch);

    /**
     * Update current instance's branch
     * @param $target_folder
     * @return mixed
     */
    abstract public function update($target_folder, $branch);
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
