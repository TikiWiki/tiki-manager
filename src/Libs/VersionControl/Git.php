<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\VersionControl;

use Exception;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Libs\Host\Command;

class Git extends VersionControlSystem
{
    private $globalOptions = [
        '--quiet',
    ];

    /**
     * GIT constructor.
     * @inheritDoc
     */
    public function __construct(Instance $instance)
    {
        parent::__construct($instance);
        $this->command = 'git';
        $this->repositoryUrl = $_ENV['GIT_TIKIWIKI_URI'];
    }

    /**
     * Get available branches within the repository
     * @return array
     */
    public function getAvailableBranches()
    {
        $versions = [];

        foreach (explode("\n", `git ls-remote $this->repositoryUrl`) as $line) {
            $parsed = explode("\t", $line);

            if (! isset($parsed[1])) {
                continue;
            }

            $line = trim($parsed[1]);
            if (empty($line)) {
                continue;
            }

            if (strpos($line, 'refs/heads/') !== false) {
                // Only list master, versions (20.x, 19.x, 18.3) and experimental branches (example: experimental/acme)
                if (!preg_match('/^refs\/heads\/(\d+\.(\d+|x)|master|experimental\/.+)$/', $line)) {
                    continue;
                }
                $versions[] = str_replace('refs/heads/', '', $line); // Example: branch/master
            }

            if (strpos($line, 'refs/tags/') !== false) {
                $versions[] = str_replace('refs/', '', $line); // example: tags/19.x
            }
        }

        sort($versions, SORT_NATURAL);
        $sortedVersions = [];

        foreach ($versions as $version) {
            $sortedVersions[] = Version::buildFake('git', $version);
        }

        return $sortedVersions;
    }

    public function getRepositoryBranch($targetFolder)
    {
        return $this->info($targetFolder);
    }

    public function exec($targetFolder, $toAppend, $forcePathOnCommand = false)
    {
        $toAppend .= ' ' . implode(' ', $this->globalOptions);

        if ($forcePathOnCommand && !empty($targetFolder)) {
            $command = sprintf('cd %s && %s %s', $targetFolder, $this->command, $toAppend);
        } else {
            $command = sprintf('%s %s', $this->command, $toAppend);
        }

        if ($this->runLocally) {
            return `$command`;
        }

        $commandInstance = new Command($command);
        $result = $this->access->runCommand($commandInstance);

        if ($result->getReturn() !== 0) {
            throw new Exception($result->getStderrContent());
        }

        return rtrim($result->getStdoutContent(), "\n");
    }

    public function clone($branchName, $targetFolder)
    {
        $branch = escapeshellarg($branchName);
        $repoUrl = escapeshellarg($this->repositoryUrl);
        $folder = escapeshellarg($targetFolder);
        return $this->exec($targetFolder, sprintf('clone --depth 1 --no-single-branch -b %s %s %s', $branch, $repoUrl, $folder));
    }

    public function revert($targetFolder)
    {
        return $this->exec($targetFolder, 'reset --hard', true);
    }

    public function pull($targetFolder)
    {
        return $this->cleanup($targetFolder) &&
            $this->exec($targetFolder, 'pull', true);
    }

    public function cleanup($targetFolder)
    {
        return $this->exec($targetFolder, 'gc', true);
    }

    public function merge($targetFolder, $branch)
    {
        die('[Merge] missing implementation for Git');
    }

    public function info($targetFolder, $raw = false)
    {
        return $this->exec($targetFolder, "rev-parse --abbrev-ref HEAD", true);
    }

    public function getRevision($targetFolder)
    {
        return $this->exec($targetFolder, "rev-parse --short HEAD", true);
    }

    public function checkoutBranch($targetFolder, $branch)
    {
        return $this->exec($targetFolder, "checkout $branch", true);
    }

    public function upgrade($targetFolder, $branch)
    {
        $this->revert($targetFolder);
        $this->exec($targetFolder, 'fetch --all', true);
        return $this->checkoutBranch($targetFolder, $branch);
    }

    public function update($targetFolder, $branch)
    {
        $this->revert($targetFolder);
        $branchInfo = $this->info($targetFolder);

        if ($this->isUpgrade($branchInfo, $branch)) {
            info("Upgrading to '{$branch}'");
            $this->upgrade($targetFolder, $branch);
        }

        info("Updating '{$branch}'");
        $this->exec($targetFolder, "pull", true);

        $this->cleanup($targetFolder);
    }
}
