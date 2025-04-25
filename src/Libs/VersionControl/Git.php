<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\VersionControl;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use TikiManager\Application\Exception\VcsException;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Libs\Helpers\VersionControl;
use TikiManager\Libs\Host\Command;

class Git extends VersionControlSystem
{
    protected const DEFAULT_GIT_REPOSITORY = 'https://gitlab.com/tikiwiki/tiki.git';
    protected $isDefultRepository = false;

    protected $command = 'git';

    protected $quiet = true;

    /**
     * GIT constructor.
     * @inheritDoc
     */
    public function __construct(Instance $instance, array $vcsOptions = [], LoggerInterface $logger = null)
    {
        parent::__construct($instance, $vcsOptions, $logger);
        $this->setRepositoryUrl($_ENV['GIT_TIKIWIKI_URI'] ?? self::DEFAULT_GIT_REPOSITORY);
        $this->setSafeDirectory($instance);
    }

    public function setRepositoryUrl($url): void
    {
        $this->repositoryUrl = $url;
        $this->isDefultRepository = ($url === self::DEFAULT_GIT_REPOSITORY);
    }

    /**
     * Get available branches within the repository
     * @return array
     */
    public function getAvailableBranches()
    {
        $versions = [];

        foreach (explode("\n", `git ls-remote --heads --tags --refs $this->repositoryUrl`) as $line) {
            $parsed = explode("\t", $line);

            if (!isset($parsed[1])) {
                continue;
            }

            $line = trim($parsed[1]);
            if (empty($line)) {
                continue;
            }

            if (strpos($line, 'refs/heads/') !== false) {
                if ($this->isDefultRepository) {
                    // For the main tiki repository only list master, versions (20.x, 19.x, 18.3) and experimental
                    // branches (example: experimental/acme).
                    if (!preg_match('/^refs\/heads\/(\d+\.(\d+|x)|master|experimental\/.+)$/', $line)) {
                        continue;
                    }
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
     * @param $isPriorityCommand
     * @return mixed|string
     * @throws VcsException
     */
    public function exec($targetFolder, $toAppend, $isPriorityCommand = false)
    {
        $command = sprintf('%s %s', $this->command, $toAppend);

        if ($isPriorityCommand && $this->access) {
            $command = $this->access->executeWithPriorityParams($command);
        }

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

        if (empty($output) && ! empty($error)) {
            $output = $error;
        }

        return rtrim($output ?? '', "\n");
    }

    public function clone($branchName, $targetFolder)
    {
        $branch = escapeshellarg($branchName);
        $repoUrl = escapeshellarg($this->repositoryUrl);
        $folder = escapeshellarg($targetFolder);
        return $this->exec(null, sprintf('clone --depth 1 -b %s %s %s', $branch, $repoUrl, $folder), true);
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
                // when bisecting, the branch is not HEAD but the branch name
                $commitHash = $this->exec($targetFolder, 'rev-parse HEAD');
                $gitCmd = "branch --contains $commitHash";
                $branchOutput = $this->exec($targetFolder, $gitCmd);
                if (!empty($branchOutput) && preg_match('/bisect started on ([\d]+\.\w+)/', $branchOutput, $matches)) {
                    return trim($matches[1]);
                }
                $output = 'HEAD';
            }
        }

        return $output;
    }

    /**
     * @param $targetFolder
     * @param $url
     * @param string $remote
     * @return mixed|string
     * @throws VcsException
     */
    public function remoteSetUrl($targetFolder, $url, $remote = 'origin')
    {
        $gitCmd = sprintf('remote set-url %s %s', $remote, $url);
        return $this->exec($targetFolder, $gitCmd);
    }

    /**
     * Set remote branch for a specific folder
     * @param $targetFolder
     * @param $branch
     * @param string $remote
     * @return mixed|string
     * @throws VcsException
     */
    public function remoteSetBranch($targetFolder, $branch, $remote = 'origin')
    {
        $gitCmd = sprintf('remote set-branches %s %s', $remote, $branch);
        return $this->exec($targetFolder, $gitCmd);
    }

    /**
     * @param $targetFolder
     * @return mixed|string
     * @throws VcsException
     */
    public function getRevision($targetFolder)
    {
        $gitCmd = 'rev-parse --short=8 --verify HEAD' . ($this->quiet ? ' --quiet' : '');
        return $this->exec($targetFolder, $gitCmd);
    }

    /**
     * @param $targetFolder
     * @param $commitId
     * @return mixed|string
     *
     * @throws VcsException
     */
    public function getDateRevision($targetFolder, $commitId)
    {
        $gitCmd = 'show -s --format=%cd --date="format:%Y-%m-%d" '. $commitId;
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

        return $this->exec($targetFolder, $gitCmd, true);
    }

    /**
     * @param $targetFolder
     * @param $branch
     * @param null $commitSHA
     * @throws VcsException
     */
    public function upgrade($targetFolder, $branch, $commitSHA = null): void
    {
        $stash = $this->canStash() &&
            (count($this->getChangedFiles($targetFolder)) || count($this->getDeletedFiles($targetFolder)));
        if ($stash) {
            $this->stash($targetFolder);
        }

        $this->checkoutBranch($targetFolder, $branch, $commitSHA);

        if ($stash) {
            $this->stashPop($targetFolder);
        }

        $this->cleanup($targetFolder);
    }

    /**
     * Update current instance's branch
     * @param string $targetFolder
     * @param string $branch
     * @param int $lag The number of days
     * @param string|null $revision The specific revision to checkout after update
     * @void
     * @throws VcsException
     */
    public function update(string $targetFolder, string $branch, int $lag = 0, ?string $revision = null)
    {
        $commitSHA = $revision;
        $fetchOptions = [];
        $time = time() - $lag * 60 * 60 * 24;
        $revisionMsg = $revision ? "with revision {$revision}" : "";
        $messageUpdate = "Updating '{$branch}' branch {$revisionMsg}";

        if (!empty($this->instance->repo_url)) {
            $this->remoteSetUrl($targetFolder, $this->instance->repo_url);
        }

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
            $messageUpdate = "Upgrading to '{$branch}' branch {$revisionMsg}";
            if (strpos($branch, 'tags/') !== 0) {
                $this->remoteSetBranch($targetFolder, $branch);
            }
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

        if ($revision && !$this->isRevisionPresent($targetFolder, $revision)) {
            $this->deepenCloneUntilRevisionPresent($targetFolder, $revision);
        }

        if (!$revision && $lag) {
            list('commit' => $commitSHA, 'date' => $date) = $this->getLastCommit($targetFolder, $branch, $time);
            $messageUpdate .= " ({$commitSHA}) at {$date}";
        }

        $this->io->writeln($messageUpdate);

        if ($isUpgrade) {
            $this->upgrade($targetFolder, $branch, $commitSHA);
            return;
        }

        $stash = $this->canStash() &&
            (count($this->getChangedFiles($targetFolder)) || count($this->getDeletedFiles($targetFolder)));
        if ($stash) {
            // Cannot use --include-untracked (due to maintenance.php and .htaccess.bck)
            $this->stash($targetFolder);
        }

        $commitSHA
            ? $this->checkoutBranch($targetFolder, $branch, $commitSHA)
            : $this->pull($targetFolder);

        if ($stash) {
            $this->stashPop($targetFolder);
        }

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

        $command = $this->access->executeWithPriorityParams($command);

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

        $command = $this->access->executeWithPriorityParams($command);

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

        $command = $this->access->executeWithPriorityParams($command);

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

        if (!preg_match('/commit (\w+).*?Date:\s+([^\r\n]*)/s', $gitLog, $matches)) {
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
        if (strpos($branch, 'tags/') === 0) {
            $branch = 'tag ' . $branch;
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

        return $this->access->fileExists($targetFolder . '/' . $file);
    }

    public function unshallow($targetFolder)
    {
        if (! $this->isShallow($targetFolder)) {
            return;
        }
        return $this->exec($targetFolder, "fetch --unshallow");
    }

    public function getVersion($targetFolder): string
    {
        $version = $this->exec($targetFolder, '--version');
        preg_match('/[\d\.]+/', $version, $matches);

        return $matches ? $matches[0] : '';
    }

    public function stash(string $targetFolder, bool $includeNonTracked = false)
    {
        $cmd = 'stash';

        $cmd .= ($includeNonTracked ? ' --include-untracked' : '');
        $cmd .= ($this->quiet ? ' --quiet' : '');

        return $this->exec($targetFolder, $cmd);
    }

    /**
     * @throws VcsException
     */
    public function stashPop(string $targetFolder, bool $revertOnFailure = true)
    {
        $cmd = 'stash pop';
        $cmd .= ($this->quiet ? ' --quiet' : '');

        try {
            return $this->exec($targetFolder, $cmd);
        } catch (\Exception $e) {
            if (!$revertOnFailure) {
                throw $e;
            }

            // Because Tiki Manager stashes with --include-untracked
            // in case of failure, reset --hard can be used.
            $this->logger->error('Failed to apply stash@{0}.', [
                'path' => $targetFolder,
                'exception' => $e,
            ]);
            $this->logger->notice('Reverting stashed changes...');

            $this->revert($targetFolder);

            return false;
        }
    }

    /**
     * @return bool
     */
    protected function canStash(): bool
    {
        return $this->vcsOptions['allow_stash'] ?? false;
    }

    /**
     * Set safe.directory in git global config
     *
     * @param Instance $instance
     * @return null
     */
    private function setSafeDirectory($instance)
    {
        $skipSafeDir = isset($_ENV['GIT_DONT_ADD_SAFEDIR']) ? (bool) $_ENV['GIT_DONT_ADD_SAFEDIR'] : false;

        if ($skipSafeDir) {
            return; // return early if we should not process safedir
        }

        $cacheTimestamp = 0;
        $cacheFile = $_ENV['CACHE_FOLDER'] . '/' . $instance->name . '.txt';
        if (file_exists($cacheFile)) {
            $cacheTimestamp = (int) file_get_contents($cacheFile);
        }

        $gitConfigFileTimestamp = 0;
        // From https://git-scm.com/docs/git-config#FILES
        $envHome = $_SERVER['HOME'] ?? (getenv('HOME') ?: '');
        $envXdgConfigHome = $_SERVER['XDG_CONFIG_HOME'] ?? (getenv('XDG_CONFIG_HOME') ?: '');
        if (empty($envXdgConfigHome)) {
            $envXdgConfigHome = $envHome . '/.config' ;
        }
        $possibleGlobalConfigFiles = [
            $envXdgConfigHome . '/git/config',
            $envHome . '/.gitconfig',
        ];
        foreach ($possibleGlobalConfigFiles as $gitConfigFile) {
            if (file_exists($gitConfigFile)) {
                $gitConfigFileTimestamp = max($gitConfigFileTimestamp, filemtime($gitConfigFile));
            }
        }
        if ($gitConfigFileTimestamp == 0) { // no file found, force refresh
            $gitConfigFileTimestamp = time();
        }

        if (! empty($instance->webroot) && $gitConfigFileTimestamp > $cacheTimestamp) {
            $command = 'config --global --add safe.directory \'' . $instance->webroot . '\'';
            try {
                $safeDirectories = $this->exec(null, 'config --list');
                if (strpos($safeDirectories, 'safe.directory=' . $instance->webroot) === false) {
                    $this->exec(null, $command);
                    file_put_contents($cacheFile, time());
                }
            } catch (\Exception $e) {
                $this->exec(null, $command);
            }
        }
    }

    /**
     * Start the git bisect session.
     *
     * @param string $targetFolder The directory where the git repository is located.
     * @param string $badCommit The commit SHA or reference considered bad.
     * @param string $goodCommit The commit SHA or reference considered good, if available.
     * @return string The output from the git command.
     */
    public function startBisect($targetFolder, $badCommit, $goodCommit)
    {
        $this->exec($targetFolder, 'bisect start');
        $badOutput = $this->markBadBisect($targetFolder, $badCommit);
        $goodOutput = $this->markGoodBisect($targetFolder, $goodCommit);
        return trim($badOutput . "\n" . $goodOutput);
    }

    /**
     * Marks a commit as good in the current git bisect session.
     *
     * @param string $targetFolder The directory where the git repository is located.
     * @param string $commitId The commit SHA or reference to mark as good.
     * @return string The output from the git command.
     */
    public function markGoodBisect($targetFolder, $commitId)
    {
        return trim($this->exec($targetFolder, sprintf('bisect good %s', escapeshellarg($commitId))));
    }

    /**
     * Marks a commit as bad in the current git bisect session.
     *
     * @param string $targetFolder The directory where the git repository is located.
     * @param string $commitId The commit SHA or reference to mark as bad.
     * @return string The output from the git command.
     */
    public function markBadBisect($targetFolder, $commitId)
    {
        return trim($this->exec($targetFolder, sprintf('bisect bad %s', escapeshellarg($commitId))));
    }

    /**
     * Resets the git bisect session, returning the repository to the pre-bisect state.
     *
     * @param string $targetFolder The directory where the git repository is located.
     * @return string The output from the git bisect reset command.
     */
    public function resetBisect($targetFolder)
    {
        return trim($this->exec($targetFolder, 'bisect reset'));
    }

    /**
     * Checks if a given revision is present in the target folder.
     *
     * This method uses the `git rev-parse --verify` command to check if the specified revision exists in the repository.
     * If the command succeeds, it means the commit is found, and the method returns true. If the command fails,
     * indicating the commit is not found, the method catches the exception and returns false.
     *
     * @param string $targetFolder The directory where the git repository is located.
     * @param string $revision The revision (e.g., commit SHA) to check for existence.
     * @return bool Returns true if the revision is present, false otherwise.
     */
    public function isRevisionPresent($targetFolder, $revision)
    {
        try {
            $this->exec($targetFolder, sprintf('rev-parse --verify %s', escapeshellarg($revision)));
            return true;
        } catch (VcsException $e) {
            return false;
        }
    }

    /**
     * Deepens a shallow clone until a specified revision is found or a maximum depth is reached.
     *
     * This method increases the depth of a shallow clone in increments, checking for the presence of
     * a specific revision at each step. If the repository is already a full clone, the deepening process
     * stops. The process also stops if the revision is found or if the maximum allowed depth is reached.
     *
     * @param string $targetFolder The directory where the git repository is located.
     * @param string $revision The revision (e.g., commit SHA) to search for in the repository.
     */
    public function deepenCloneUntilRevisionPresent($targetFolder, $revision)
    {
        $found = $this->isRevisionPresent($targetFolder, $revision);
        $maxDepth = 1000;
        $deepenAmount = 100;
        $currentDepth = 0;

        while (! $found && $currentDepth < $maxDepth) {
            if (! $this->isShallow($targetFolder)) {
                $this->logger->info("The repository is already a full clone. Stopping deepening process...");
                break;
            }

            $this->exec($targetFolder, "fetch --deepen=$deepenAmount");
            $found = $this->isRevisionPresent($targetFolder, $revision);
            $currentDepth += $deepenAmount;

            if ($found) {
                $this->logger->info("Revision $revision found after deepening the clone to a depth of $currentDepth.");
            } elseif ($currentDepth >= $maxDepth) {
                $this->logger->warning("Reached maximum deepening depth of $maxDepth without finding revision $revision.");
            }
        }
    }

    public function isBranchBelongsToRepo($branch, $repoUrl)
    {
        $this->setRepositoryUrl($repoUrl);
        $availableVersions = $this->getAvailableBranches();
        foreach ($availableVersions as $version) {
            if ($version instanceof Version) {
                $gitBranch = VersionControl::formatBranch($version->branch, 'git');
                if ($gitBranch === $branch) {
                    return true;
                }
            }
        }

        return false;
    }
}
