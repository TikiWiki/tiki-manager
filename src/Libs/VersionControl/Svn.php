<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\VersionControl;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use TikiManager\Application\Exception\VcsConflictException;
use TikiManager\Application\Exception\VcsException;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Libs\Host\Command;

class Svn extends VersionControlSystem
{
    /*
     * @var string
     */
    protected static $stashFile;

    const SVN_TEMP_FOLDER_PATH = DIRECTORY_SEPARATOR . '.svn' . DIRECTORY_SEPARATOR . 'tmp';

    private $globalOptions = [
        '--non-interactive',
    ];

    /**
     * SVN constructor.
     * @inheritDoc
     */
    public function __construct(Instance $instance, array $vcsOptions = [], LoggerInterface $logger = null)
    {
        parent::__construct($instance, $vcsOptions, $logger);
        $this->command = 'svn';
        $this->repositoryUrl = $_ENV['SVN_TIKIWIKI_URI'];
    }

    /**
     * Gets the specific repository URL for this class
     * @return string
     */
    protected function getRepositoryUrl()
    {
        return $_ENV['SVN_TIKIWIKI_URI'];
    }

    public function getRepositoryBranch($targetFolder)
    {
        $info = $this->info($targetFolder);
        $url = $info['url'];
        $root = $info['repository']['root'];
        $branchIndex = strlen($root);
        $branchName = substr($url, $branchIndex);
        $branchName = trim($branchName, '/');

        return $branchName;
    }

    public function getAvailableBranches()
    {
        $versions = [];
        $versionsTemp = [];

        foreach (explode("\n", `svn ls $this->repositoryUrl/tags`) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (substr($line, -1) == '/' && ctype_digit($line[0])) {
                $versionsTemp[] = 'svn:tags/' . substr($line, 0, -1);
            }
        }
        sort($versionsTemp, SORT_NATURAL);
        $versions = array_merge($versions, $versionsTemp);

        $versionsTemp = [];
        foreach (explode("\n", `svn ls $this->repositoryUrl/branches`) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (substr($line, -1) == '/' && ctype_digit($line[0])) {
                $versionsTemp[] = 'svn:branches/' . substr($line, 0, -1);
            }
        }
        sort($versionsTemp, SORT_NATURAL);
        $versions = array_merge($versions, $versionsTemp);

        // Trunk as last option
        $versions[] = 'svn:trunk';

        $versionsSorted = [];
        foreach ($versions as $version) {
            list($type, $branch) = explode(':', $version);
            $versionsSorted[] = Version::buildFake($type, $branch);
        }

        return $versionsSorted;
    }

    public function exec($targetFolder, $toAppend)
    {
        static $tmpFolderChecked;
        if ((empty($tmpFolderChecked) || !in_array(
            $targetFolder,
            $tmpFolderChecked
        )) && $this->ensureTempFolder($targetFolder)) {
            $tmpFolderChecked[] = $targetFolder;
            try {
                $this->exec($targetFolder, 'upgrade');
            } catch (VcsException $e) {
                // Ignore VcsException exceptions when trying to upgrade since it may already be updated.
            }
        }

        $globalOptions = implode(' ', $this->globalOptions);
        $command = implode(' ', [$this->command, $globalOptions, $toAppend]);

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
        $branch = $this->repositoryUrl . "/$branchName";
        $branch = str_replace('/./', '/', $branch);
        $branch = escapeshellarg($branch);

        return $this->exec($targetFolder, "co $branch $targetFolder");
    }

    public function revert($targetFolder)
    {
        $this->cleanup($targetFolder);
        return $this->exec($targetFolder, "revert $targetFolder --recursive");
    }

    public function pull($targetFolder)
    {
        $this->cleanup($targetFolder);
        return $this->exec($targetFolder, "up $targetFolder");
    }

    public function cleanup($targetFolder)
    {
        return $this->exec($targetFolder, "cleanup $targetFolder");
    }

    public function merge($targetFolder, $branch)
    {
        $toAppend = '';

        if (preg_match('/^(\w+):(\w+)$/', $branch)
            || preg_match('/^(\w+):({"\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}"})$/', $branch)
        ) {
            $toAppend .= "--revision {$branch} ";
        } elseif (is_numeric($branch)) {
            $toAppend .= "--change {$branch}";
        } else {
            $toAppend = $this->getBranchUrl($branch);
        }

        return $this->exec($targetFolder, "merge $toAppend --accept theirs-full --allow-mixed-revisions --dry-run .");
    }

    public function info($targetFolder, $raw = false)
    {
        $cmd = "info $targetFolder";
        if (! $raw) {
            $cmd .= ' --xml';
        }

        $xml = $this->exec($targetFolder, $cmd);

        if ($raw) {
            return $xml;
        }

        $xml = simplexml_load_string($xml);

        $curNode = $xml->entry;
        $result = [];
        $stack = [
            [$curNode, &$result]
        ];

        while (! empty($stack)) {
            $stack_item = array_pop($stack);
            $curNode = $stack_item[0];
            $output = &$stack_item[1];

            $nodeName = $curNode->getName();
            $node_children = $curNode->children();

            if (empty($node_children)) {
                $value = sprintf('%s', $curNode);
                $value = is_numeric($value) ? floatval($value) : $value;
                $output[ $nodeName ] = $value;
                continue;
            } else {
                $output[ $nodeName ] = [];

                foreach ($node_children as $nodeChild) {
                    $stack[] = [$nodeChild, &$output[ $nodeName ]];
                }
            }
        }

        $result = !empty($result['entry']) ? $result['entry'] : [];
        return $result;
    }

    public function getRevision($targetFolder)
    {
        $info = $this->info($targetFolder, true);

        if (! empty($info)) {
            preg_match('/(.*Rev(?:ision)?:\s+)(.*)/', $info, $matches);
            return $matches[2];
        }

        return 0;
    }

    public function getDateRevision($targetFolder, $commitId)
    {
        $info = $this->info($targetFolder, true);

        if (! empty($info)) {
            preg_match('/(.*Date:\s+)(.*)/', $info, $matches);
            $date_parse = date_parse($matches[2]);
            return $date_parse['year'] . '-' . $date_parse['month'] . '-' . $date_parse['day'];
        }
        return '';
    }

    /**
     * @param $targetFolder
     * @param $branch
     * @return mixed|string
     * @throws VcsConflictException
     * @throws VcsException
     */
    public function checkoutBranch($targetFolder, $branch)
    {
        $output = $this->exec($targetFolder, "switch $branch $targetFolder --accept theirs-full");
        if (preg_match('/Summary of conflicts:/i', $output)) {
            throw new VcsConflictException('SVN CONFLICTS FOUND: ' . $output);
        }

        return $output;
    }

    public function upgrade($targetFolder, $branch): void
    {
        $stash = $this->canStash() && count($this->getChangedFiles($targetFolder));
        if ($stash) {
            $this->stash($targetFolder);
        }

        $this->checkoutBranch($targetFolder, $branch);

        if ($stash) {
            $this->stashPop($targetFolder);
        }

        $this->cleanup($targetFolder);
    }

    public function update(string $targetFolder, string $branch, int $lag = 0)
    {
        $info = $this->info($targetFolder);
        $root = $info['repository']['root'];
        $url = $info['url'];
        $branchUrl = $root . "/" . $branch;

        if ($root != $this->repositoryUrl) {
            $this->io->error("Trying to upgrade '{$this->repositoryUrl}' to different repository: {$root}");
            return false;
        }

        try {
            $conflicts = $this->merge($targetFolder, 'BASE:HEAD');
        } catch (Exception $e) {
            // SVN is unable to merge when there are deleted files but not removed from SVN index.
            // Example:
            // svn: E195016: Merge tracking not allowed with missing subtrees; try restoring these items first:
            // temp/README
            // temp/web.config
            // temp/index.php

            $this->io->error($e->getMessage());
            $conflicts = '';
        }

        if (strlen(trim($conflicts)) > 0 &&
            preg_match('/conflicts:/i', $conflicts)) {
            throw new VcsConflictException("SVN MERGE: $conflicts");
        }

        if ($lag) {
            $lag = date('{"Y-m-d H:i"}', time() - $lag * 60 * 60 * 24);
            $this->io->writeln("Updating to '{$branch}@{$lag}'");
            $this->exec($targetFolder, "update $targetFolder --revision $lag svn:$branchUrl --accept postpone");
            $this->cleanup($targetFolder);
            return;
        }

        if ($this->isUpgrade($url, $branch)) {
            $this->io->writeln("Upgrading to '{$branch}' branch");
            $this->upgrade($targetFolder, $branchUrl);
            return;
        }

        $this->io->writeln("Updating '{$branch}' branch");
        $this->exec($targetFolder, "update $targetFolder --accept theirs-full --force");

        $this->cleanup($targetFolder);
    }

    public function ensureTempFolder($targetFolder)
    {
        // If .svn is not set, cannot ensure temp folder (not a .svn repository, yet)
        if (!$this->access->fileExists(dirname($targetFolder . self::SVN_TEMP_FOLDER_PATH))) {
            return false;
        }

        if (!$this->access->fileExists($targetFolder . self::SVN_TEMP_FOLDER_PATH)) {
            $path = $this->access->getInterpreterPath();
            $script = sprintf("mkdir('%s', 0777, true);", $targetFolder . self::SVN_TEMP_FOLDER_PATH);
            $this->access->createCommand($path, ["-r {$script}"])->run();
        }

        return true;
    }

    public function isFileVersioned($targetFolder, $fileName)
    {
        try {
            $this->exec($targetFolder, "info $fileName");
        } catch (\Exception $exception) {
            return false;
        }
        return true;
    }

    public function hasRemote($targetFolder, $branch)
    {
        return true;
    }

    public function getChangedFiles($folder)
    {
        $allFiles = $this->exec($folder, 'status', true);

        $regex = '/(?:^M)\s*(.*)$/m';

        \preg_match_all($regex, $allFiles, $matches);

        return $matches[1] ?? [];
    }

    public function getDeletedFiles($folder)
    {
        $allFiles = $this->exec($folder, 'status', true);

        $regex = '/(?:^\!)\s*(.*)$/m';

        \preg_match_all($regex, $allFiles, $matches);

        return $matches[1] ?? [];
    }

    public function getUntrackedFiles($folder, $includeIgnore = false)
    {
        $command = 'status' . ($includeIgnore ? ' --no-ignore' : '');

        $allFiles = $this->exec($folder, $command, true);

        $regex = $includeIgnore ? '/(?:^I|^\?)\s*(.*)$/m' : '/(?:^\?)\s*(.*)$/m';

        \preg_match_all($regex, $allFiles, $matches);

        return $matches[1] ?? [];
    }

    public function stash(string $targetFolder): void
    {
        static::$stashFile = 'svn_diff_' . time() . '.patch';
        $cmd = 'diff -x "-w --ignore-eol-style"> ' . static::$stashFile;

        $this->exec($targetFolder, $cmd);
        $this->revert($targetFolder);
    }

    /**
     * @throws VcsException
     */
    public function stashPop(string $targetFolder)
    {
        $cmd = 'patch --ignore-whitespace ' . static::$stashFile ;

        try {
            $output = $this->exec($targetFolder, $cmd);

            if (preg_match('/Summary of conflicts:/i', $output)) {
                throw new VcsConflictException('Conflicts found: ' . PHP_EOL . $output);
            }

            // Changes applied, safe to remove patch file
            $this->access->deleteFile(static::$stashFile);
        } catch (\Exception $e) {
            $this->logger->error('Failed to apply {file}.' . PHP_EOL . '{message}', [
                'file' => static::$stashFile,
                'path' => $targetFolder,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * @return bool
     */
    protected function canStash(): bool
    {
        return $this->vcsOptions['allow_stash'] ?? false;
    }
}
