<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\VersionControl;

use Symfony\Component\Process\Process;
use TikiManager\Application\Exception\VcsException;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Libs\Host\Command;

class Git extends VersionControlSystem
{
    protected $command = 'git';

    protected $quiet = true;

    /**
     * GIT constructor.
     * @inheritDoc
     */
    public function __construct(Instance $instance)
    {
        parent::__construct($instance);
        $this->setRepositoryUrl($_ENV['GIT_TIKIWIKI_URI']);
    }

    public function setRepositoryUrl($url): void
    {
        $this->repositoryUrl = $url;
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

            if (!isset($parsed[1])) {
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

            if (strpos($line, 'refs/tags/tags') !== false) {
                $versions[] = str_replace('refs/tags/', '', $line); // Example: tags/tags/21.0
                continue;
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

    /**
     * @param $targetFolder
     * @return mixed|string
     * @throws VcsException
     */
    public function getRepositoryBranch($targetFolder)
    {
        return $this->info($targetFolder);
    }

    /**
     * @param $targetFolder
     * @param $fileName
     * @return bool
     */
    public function isFileVersioned($targetFolder, $fileName)
    {
        try {
            $this->exec($targetFolder, "ls-files --error-unmatch $fileName");
        } catch (VcsException $ex) {
            return false;
        }
        return true;
    }

    /**
     * @param $targetFolder
     * @param $toAppend
     * @return mixed|string
     * @throws VcsException
     */
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
        return $this->exec(null, sprintf('clone --depth 1 -b %s %s %s', $branch, $repoUrl, $folder));
    }

    /**
     * @param $targetFolder
     * @return mixed|string
     * @throws VcsException
     */
    public function revert($targetFolder)
    {
        $gitCmd = 'reset --hard' . ($this->quiet ? ' --quiet' : '');
        return $this->exec($targetFolder, $gitCmd);
    }

    /**
     * @param $targetFolder
     * @return mixed|string
     * @throws VcsException
     */
    public function pull($targetFolder)
    {
        $gitCmd = 'pull' . ($this->quiet ? ' --quiet' : '');
        return $this->exec($targetFolder, $gitCmd);
    }

    /**
     * @param $targetFolder
     * @return mixed|string
     * @throws VcsException
     */
    public function cleanup($targetFolder)
    {
        $gitCmd = 'gc' . ($this->quiet ? ' --quiet' : '');
        return $this->exec($targetFolder, $gitCmd);
    }

    /**
     * @param $targetFolder
     * @param $branch
     * @param null $commitSHA
     * @return mixed|string
     * @throws VcsException
     */
    public function merge($targetFolder, $branch, $commitSHA = null)
    {
        $gitCmd = "merge $branch";
        $gitCmd .= $commitSHA ? " $commitSHA" : '';
        $gitCmd .= $this->quiet ? ' --quiet' : '';

        return $this->exec($targetFolder, $gitCmd);
    }

    /**
     * @param $targetFolder
     * @param false $raw
     * @return mixed|string
     * @throws VcsException
     */
    public function info($targetFolder, $raw = false)
    {
        $gitCmd = 'rev-parse --abbrev-ref HEAD' . ($this->quiet ? ' --quiet' : '');
        $output = $this->exec($targetFolder, $gitCmd);

        // When using tags, the HEAD is detached (this will calculate if it belongs to a tag)
        if ($output == 'HEAD') {
            $gitCmd = 'name-rev --name-only HEAD';
            $output = $this->exec($targetFolder, $gitCmd);

            if (preg_match('/^tags\\/[^\^\~]*/', $output, $matches)) {
                $output = str_replace('tags/tags', 'tags', $matches[0]);
            } else {
                $output = 'HEAD';
            }
        }

        return $output;
    }

    /**
     * @param $targetFolder
     * @param $branch
     * @param string $remote
     * @throws VcsException
     */
    public function remoteSetBranch($targetFolder, $branch, $remote = 'origin'): void
    {
        $gitCmd = sprintf('remote set-branches %s %s', $remote, $branch);
        $this->exec($targetFolder, $gitCmd);
    }

    /**
     * @param $targetFolder
     * @return mixed|string
     * @throws VcsException
     */
    public function getRevision($targetFolder)
    {
        $gitCmd = 'rev-parse --short HEAD' . ($this->quiet ? ' --quiet' : '');
        return $this->exec($targetFolder, $gitCmd);
    }

    /**
     * @param $targetFolder
     * @param $branch
     * @param null $commitSHA
     * @return mixed|string
     * @throws VcsException
     */
    public function checkoutBranch($targetFolder, $branch, $commitSHA = null)
    {
        $gitCmd = $commitSHA ? "checkout -B $branch $commitSHA" : "checkout $branch";
        $gitCmd .= $this->quiet ? ' --quiet' : '';

        return $this->exec($targetFolder, $gitCmd);
    }

    /**
     * @param $targetFolder
     * @param $branch
     * @param null $commitSHA
     * @throws VcsException
     */
    public function upgrade($targetFolder, $branch, $commitSHA = null): void
    {
        $this->revert($targetFolder);
        $this->checkoutBranch($targetFolder, $branch, $commitSHA);
        $this->cleanup($targetFolder);
    }

    /**
     * Update current instance's branch
     * @param string $targetFolder
     * @param string $branch
     * @param int $lag The number of days
     * @void
     * @throws VcsException
     */
    public function update(string $targetFolder, string $branch, int $lag = 0)
    {
        $commitSHA = null;
        $fetchOptions = [];
        $time = time() - $lag * 60 * 60 * 24;
        $messageUpdate = "Updating '{$branch}' branch";

        $branchInfo = $this->info($targetFolder);
        $isUpgrade = $this->isUpgrade($branchInfo, $branch);
        $isShallow = $this->isShallow($targetFolder);

        if ($isUpgrade && $isShallow) {
            $fetchOptions['--depth'] = 1;
        }

        $fetch = function () use ($targetFolder, $branch, $fetchOptions) {
            $this->fetch($targetFolder, $branch, 'origin', $fetchOptions);
        };

        if ($isUpgrade) {
            $messageUpdate = "Upgrading to '{$branch}' branch";
            $this->remoteSetBranch($targetFolder, $branch);
        }

        if ($lag && $isShallow) {
            $fetch = function () use ($targetFolder, $branch, $time) {
                $gitVersion = $this->getVersion($targetFolder);

                if (version_compare($gitVersion, '2.11.0', '<')) {
                    // LARGE NUMBER OF COMMITS as --shallow-since/--deepen is not supported
                    $this->fetch($targetFolder, $branch, 'origin', ['--depth' => 200]);
                    return;
                }

                $this->fetch($targetFolder, $branch, 'origin', ['--shallow-since' => date('Y-m-d H:i', $time)]);
                $this->fetch($targetFolder, $branch, 'origin', ['--deepen' => 1]);
            };
        }

        $fetch();

        if ($lag) {
            list('commit' => $commitSHA, 'date' => $date) = $this->getLastCommit($targetFolder, $branch, $time);
            $messageUpdate .= " ({$commitSHA}) at {$date}";
        }

        $this->io->writeln($messageUpdate);

        if ($isUpgrade) {
            $this->upgrade($targetFolder, $branch, $commitSHA);
            return;
        }

        $commitSHA
            ? $this->checkoutBranch($targetFolder, $branch, $commitSHA)
            : $this->pull($targetFolder);

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

    public function hasRemote($targetFolder, $branch)
    {
        $output = $this->exec(
            $targetFolder,
            'ls-remote --heads --exit-code origin ' . $branch
        );
        return !empty($output);
    }

    public function getChangedFiles($folder, $exclude = [])
    {
        $command = $this->command . ' ls-files --modified';

        $command = sprintf('cd %s && %s', $folder, $command);

        $commandInstance = new Command($command);
        $result = $this->access->runCommand($commandInstance);

        if ($result->getReturn() !== 0) {
            throw new VcsException($result->getStderrContent());
        }

        $output = $result->getStdoutContent();
        $output = trim($output);

        return empty($output) ? [] : array_values(explode(PHP_EOL, $output));
    }

    public function getDeletedFiles($folder)
    {
        $command = $this->command . ' ls-files -d';

        $command = sprintf('cd %s && %s', $folder, $command);

        $commandInstance = new Command($command);
        $result = $this->access->runCommand($commandInstance);

        if ($result->getReturn() !== 0) {
            throw new VcsException($result->getStderrContent());
        }

        $output = $result->getStdoutContent();
        $output = trim($output);

        return empty($output) ? [] : array_values(explode(PHP_EOL, $output));
    }

    public function getUntrackedFiles($folder, $includeIgnore = false)
    {
        $command = $this->command . ' ls-files --others';

        if (!$includeIgnore) {
            $command .= ' --exclude-standard';
        }

        $command = sprintf('cd %s && %s', $folder, $command);

        $commandInstance = new Command($command);
        $result = $this->access->runCommand($commandInstance);

        if ($result->getReturn() !== 0) {
            throw new VcsException($result->getStderrContent());
        }

        $output = $result->getStdoutContent();
        $output = trim($output);

        return empty($output) ? [] : array_values(explode(PHP_EOL, $output));
    }

    /**
     * @param string $targetFolder
     * @param string $branch
     * @param array $options
     * @return mixed|string
     * @throws VcsException
     */
    public function log(string $targetFolder, string $branch, array $options = [])
    {
        $logOptions = implode(' ', $options);

        $gitCmd = "log $logOptions $branch";
        return $this->exec($targetFolder, $gitCmd);
    }

    /**
     * @param string $targetFolder
     * @param string $branch
     * @param int|null $timestamp
     * @return array
     * @throws VcsException
     */
    public function getLastCommit(string $targetFolder, string $branch, $timestamp = null): array
    {
        $lag = date('Y-m-d H:i', $timestamp ?? time());
        $options = [
            '-1',
            sprintf('--before=%s', escapeshellarg($lag))
        ];

        $gitLog = $this->log($targetFolder, 'origin/' . $branch, $options);

        if (!$gitLog) {
            throw new VcsException('Git log returned with empty output');
        }

        if (!preg_match('/commit (\w+).*Date:\s+([^\\n]*)/s', $gitLog, $matches)) {
            throw new VcsException('Unable to parse Git log output');
        }

        return [
            'commit' => $matches[1],
            'date' => $matches[2],
        ];
    }

    public function fetch($targetFolder, $branch = null, $remote = 'origin', $options = [])
    {
        $cmdOptions = [];
        foreach ($options as $option => $value) {
            $cmdOptions[] = $option . ($value ? '=' . escapeshellarg($value) : '');
        }

        $cmd = sprintf('fetch %s %s', $remote, $branch);
        $cmd .= ' ' . implode(' ', $cmdOptions);
        $cmd .= ($this->quiet ? ' --quiet' : '');

        return $this->exec($targetFolder, $cmd);
    }

    public function isShallow($targetFolder): bool
    {
        $file = '.git/shallow';

        if ($this->runLocally) {
            return file_exists($targetFolder . '/' . $file);
        }

        return $this->access->fileExists($file);
    }

    public function getVersion($targetFolder): string
    {
        $version = $this->exec($targetFolder, '--version');
        preg_match('/[\d\.]+/', $version, $matches);

        return $matches ? $matches[0] : '';
    }
}
