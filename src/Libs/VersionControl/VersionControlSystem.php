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
use TikiManager\Application\Exception\VcsException;
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
    public function __construct(Instance $instance, array $vcsOptions = [], ?LoggerInterface $logger = null)
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
     * retuns VCS for a given instance, throws an exception if type is unsupported
     * @param Instance $instance
     * @return VersionControlSystem VCS type of the Instance
     * @throws VcsException Unsupported VCS type
     */
    public static function getVersionControlSystem(Instance $instance)
    {
        $type = $instance->vcs_type ?? $_ENV['DEFAULT_VCS'];
        $vcsInstance = null;

        switch (strtoupper($type)) {
            case 'GIT':
                $vcsInstance = new Git($instance);
                break;
            case 'SRC':
                $vcsInstance = new Src($instance);
                break;
            default:
                throw new VcsException("Unsupported VCS type: $type. Please update your instance vcs type or .env configuration to use a supported VCS type (GIT or SRC).");
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
     * Start the git bisect session.
     *
     * @param string $targetFolder The directory where the git repository is located.
     * @param string $badCommit The commit SHA or reference considered bad.
     * @param string $goodCommit The commit SHA or reference considered good, if available.
     * @return string The output from the git command.
     * @throws VcsException
     */
    abstract public function startBisect($targetFolder, $badCommit, $goodCommit);

    /**
     * Marks a commit as good in the current git bisect session.
     *
     * @param string $targetFolder The directory where the git repository is located.
     * @param string $commitId The commit SHA or reference to mark as good.
     * @return string The output from the git command.
     * @throws VcsException
     */
    abstract public function markGoodBisect($targetFolder, $commitId);

    /**
     * Marks a commit as bad in the current git bisect session.
     *
     * @param string $targetFolder The directory where the git repository is located.
     * @param string $commitId The commit SHA or reference to mark as bad.
     * @return string The output from the git command.
     * @throws VcsException
     */
    abstract public function markBadBisect($targetFolder, $commitId);

    /**
     * Resets the git bisect session, returning the repository to the pre-bisect state.
     *
     * @param string $targetFolder The directory where the git repository is located.
     * @return string The output from the git bisect reset command.
     * @throws VcsException
     */
    abstract public function resetBisect($targetFolder);

    /**
     * Checks for revision is present or not
     *
     * @param string $targetFolder The directory where the repository is located.
     * @param string $revision The revision identifier (commit hash, tag, or Git revision number).
     * @return bool
     */
    abstract public function isRevisionPresent($targetFolder, $revision);

    /**
     * Clone until specific revision is found
     *
     * @param string $targetFolder The directory where the repository is located.
     * @param string $revision The revision identifier (commit hash, tag, or Git revision number).
     */
    abstract public function deepenCloneUntilRevisionPresent($targetFolder, $revision);

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

    abstract public function isBranchBelongsToRepo($branch, $repoUrl);
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
