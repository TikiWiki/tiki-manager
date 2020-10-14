<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\VersionControl;

use Exception;
use Symfony\Component\Process\Process;
use TikiManager\Application\Exception\VcsException;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Libs\Host\Command;

class Git extends VersionControlSystem
{
    protected $quiet = true;

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

    public function isFileVersioned($targetFolder, $fileName)
    {
        try {
            $this->exec($targetFolder, "ls-files --error-unmatch $fileName");
        } catch (VcsException $ex) {
            return false;
        }
        return true;
    }

    public function exec($targetFolder, $toAppend)
    {
        $command = sprintf('%s %s', $this->command, $toAppend);

        if (!empty($targetFolder)) {
            $command = sprintf('cd %s && ', $targetFolder) . $command;
        }

        if ($this->runLocally) {
            $cmd = Process::fromShellCommandline($command, null, null, null, 1800);  // 30min tops
            $cmd->run();
            $output = $cmd->getOutput();
            $error = $cmd->getErrorOutput();
            $exitCode = $cmd->getExitCode();
        } else {
            $commandInstance = new Command($command);
            $result = $this->access->runCommand($commandInstance);

            $output = $result->getStdoutContent();
            $error = $result->getStderrContent();
            $exitCode = $result->getReturn();
        }

        if ($exitCode !== 0) {
            throw new VcsException($error);
        }

        return rtrim($output, "\n");
    }

    public function clone($branchName, $targetFolder)
    {
        $branch = escapeshellarg($branchName);
        $repoUrl = escapeshellarg($this->repositoryUrl);
        $folder = escapeshellarg($targetFolder);
        return $this->exec(null, sprintf('clone --depth 1 --no-single-branch -b %s %s %s', $branch, $repoUrl, $folder));
    }

    public function revert($targetFolder)
    {
        $gitCmd = 'reset --hard' . ($this->quiet ? ' --quiet' : '');
        return $this->exec($targetFolder, $gitCmd);
    }

    public function pull($targetFolder)
    {
        $gitCmd = 'pull' . ($this->quiet ? ' --quiet' : '');
        return $this->cleanup($targetFolder) &&
            $this->exec($targetFolder, $gitCmd);
    }

    public function cleanup($targetFolder)
    {
        $gitCmd = 'gc' . ($this->quiet ? ' --quiet' : '');
        return $this->exec($targetFolder, $gitCmd);
    }

    public function merge($targetFolder, $branch)
    {
        die('[Merge] missing implementation for Git');
    }

    public function info($targetFolder, $raw = false)
    {
        $gitCmd = 'rev-parse --abbrev-ref HEAD' . ($this->quiet ? ' --quiet' : '');
        return $this->exec($targetFolder, $gitCmd);
    }

    public function getRevision($targetFolder)
    {
        $gitCmd = 'rev-parse --short HEAD' . ($this->quiet ? ' --quiet' : '');
        return $this->exec($targetFolder, $gitCmd);
    }

    public function checkoutBranch($targetFolder, $branch)
    {
        $gitCmd = "checkout $branch" . ($this->quiet ? ' --quiet' : '');
        return $this->exec($targetFolder, $gitCmd);
    }

    public function upgrade($targetFolder, $branch)
    {
        $this->revert($targetFolder);

        $gitCmd = 'fetch --all' . ($this->quiet ? ' --quiet' : '');
        $this->exec($targetFolder, $gitCmd);

        return $this->checkoutBranch($targetFolder, $branch);
    }

    public function update($targetFolder, $branch)
    {
        $this->revert($targetFolder);
        $branchInfo = $this->info($targetFolder);

        if ($this->isUpgrade($branchInfo, $branch)) {
            $this->io->writeln("Upgrading to '{$branch}' branch");
            $this->upgrade($targetFolder, $branch);
        }

        $this->io->writeln("Updating '{$branch}' branch");
        $this->pull($targetFolder);

        $this->cleanup($targetFolder);
    }

    /**
     * @inheritDoc
     */
    public function isUpgrade($current, $branch)
    {
        return $current !== $branch;
    }

    /**
     * @param bool $quiet
     */
    public function setQuiet(bool $quiet): void
    {
        $this->quiet = $quiet;
    }
}
