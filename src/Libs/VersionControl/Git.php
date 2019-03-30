<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\VersionControl;

use TikiManager\Application\Version;

class Git extends VersionControlSystem
{
    /**
     * GIT constructor.
     * @param $access
     */
    public function __construct($access)
    {
        parent::__construct($access);
        $this->command = 'git';
        $this->repository_url = GIT_TIKIWIKI_URI;
    }

    /**
     * Get available branches within the repository
     * @return array
     */
    public function getAvailableBranches()
    {
        $versions = [];

        foreach (explode("\n", `git ls-remote $this->repository_url`) as $line) {
            $parsed = explode("\t", $line);

            if (! isset($parsed[1])) {
                continue;
            }

            $line = trim($parsed[1]);
            if (empty($line)) {
                continue;
            }

            if (strpos($line, 'refs/heads/') !== false) {
                $versions[] = str_replace('refs/heads/', '', $line); // Example: branch/master
            }

            if (strpos($line, 'refs/tags/') !== false) {
                $versions[] = str_replace('refs/', '', $line); // example: tags/19.x
            }
        }

        sort($versions, SORT_NATURAL);
        $sorted_versions = [];

        foreach ($versions as $version) {
            $sorted_versions[] = Version::buildFake('git', $version);
        }

        return $sorted_versions;
    }


    public function getRepositoryBranch($target_folder)
    {
        return $this->info($target_folder);
    }

    public function exec($target_folder, $to_append, $force_path_on_command = false)
    {
        if ($force_path_on_command) {
            $this->access->chdir($target_folder);
        }

        return $this->access->shellExec(sprintf('%s %s', $this->command, $to_append));
    }

    public function clone($branch_name, $target_folder)
    {
        $branch = escapeshellarg($branch_name);
        $repoUrl = escapeshellarg($this->repository_url);
        $folder = escapeshellarg($target_folder);
        return $this->exec($target_folder, sprintf('clone --depth 1 --no-single-branch -b %s %s %s', $branch, $repoUrl, $folder));
    }

    public function revert($target_folder)
    {
        return $this->exec($target_folder, 'reset', true);
    }

    public function pull($target_folder)
    {
        return $this->cleanup($target_folder) &&
            $this->exec($target_folder, 'pull', true);
    }

    public function cleanup($target_folder)
    {
        return $this->exec($target_folder, 'gc', true);
    }

    public function merge($target_folder, $branch)
    {
        die('[Merge] missing implementation for Git');
    }

    public function info($target_folder, $raw = false)
    {
        return $this->exec($target_folder, "rev-parse --abbrev-ref HEAD", true);
    }

    public function getRevision($target_folder)
    {
        return $this->exec($target_folder, "rev-parse --short HEAD", true);
    }

    public function checkoutBranch($target_folder, $branch)
    {
        return $this->exec($target_folder, "checkout $branch", true);
    }

    public function upgrade($target_folder, $branch)
    {
        $this->revert($target_folder);
        return $this->checkoutBranch($target_folder, $branch);
    }

    public function update($target_folder, $branch)
    {
        $this->revert($target_folder);
        $branch_info = $this->info($target_folder);

        if ($this->isUpgrade($branch_info, $branch)) {
            info("Upgrading to '{$branch}'");
            $this->upgrade($target_folder, $branch);
        }

        info("Updating '{$branch}'");
        $this->exec($target_folder, "pull", true);

        $this->cleanup($target_folder);
    }
}
