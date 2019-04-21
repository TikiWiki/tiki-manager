<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\VersionControl;

use TikiManager\Application\Version;

class Svn extends VersionControlSystem
{
    const SVN_TEMP_FOLDER_PATH = DIRECTORY_SEPARATOR . '.svn' . DIRECTORY_SEPARATOR . 'tmp';

    /**
     * SVN constructor.
     * @param $access
     */
    public function __construct($access)
    {
        parent::__construct($access);
        $this->command = 'svn';
        $this->repository_url = SVN_TIKIWIKI_URI;
    }

    /**
     * Gets the specific repository URL for this class
     * @return string
     */
    protected function getRepositoryUrl()
    {
        return SVN_TIKIWIKI_URI;
    }

    public function getRepositoryBranch($target_folder)
    {
        $info = $this->info($target_folder);
        $url = $info['url'];
        $root = $info['repository']['root'];
        $branch_index = strlen($root);
        $branch_name = substr($url, $branch_index);
        $branch_name = trim($branch_name, '/');

        return $branch_name;
    }

    public function getAvailableBranches()
    {
        $versions = [];
        $versionsTemp = [];

        foreach (explode("\n", `svn ls $this->repository_url/tags`) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (substr($line, -1) == '/' && ctype_digit($line{0})) {
                $versionsTemp[] = 'svn:tags/' . substr($line, 0, -1);
            }
        }
        sort($versionsTemp, SORT_NATURAL);
        $versions = array_merge($versions, $versionsTemp);

        $versionsTemp = [];
        foreach (explode("\n", `svn ls $this->repository_url/branches`) as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (substr($line, -1) == '/' && ctype_digit($line{0})) {
                $versionsTemp[] = 'svn:branches/' . substr($line, 0, -1);
            }
        }
        sort($versionsTemp, SORT_NATURAL);
        $versions = array_merge($versions, $versionsTemp);

        // Trunk as last option
        $versions[] = 'svn:trunk';

        $versions_sorted = [];
        foreach ($versions as $version) {
            list($type, $branch) = explode(':', $version);
            $versions_sorted[] = Version::buildFake($type, $branch);
        }

        return $versions_sorted;
    }

    public function exec($target_folder, $to_append, $force_path_on_command = false)
    {
        return $this->access->shellExec($this->command . " " . $to_append);
    }

    public function clone($branch_name, $target_folder)
    {
        $branch = $this->repository_url . "/$branch_name";
        $branch = str_replace('/./', '/', $branch);
        $branch = escapeshellarg($branch);

        return $this->exec($target_folder, "co $branch $target_folder");
    }

    public function revert($target_folder)
    {
        return $this->exec($target_folder, "reset $target_folder --recursive")
            && $this->cleanup($target_folder);
    }

    public function pull($target_folder)
    {
        return $this->cleanup($target_folder) &&
            $this->exec($target_folder, "up --non-interactive $target_folder");
    }

    public function cleanup($target_folder)
    {
        return $this->exec($target_folder, "cleanup $target_folder");
    }

    public function merge($target_folder, $branch)
    {
        $to_append = '';

        if (preg_match('/^(\w+):(\w+)$/', $branch)) {
            $to_append .= "--revision {$branch} ";
        } elseif (is_numeric($branch)) {
            $to_append .= "--change {$branch}";
        } else {
            $branch = $this->getBranchUrl($branch);
        }

        return $this->exec($target_folder, "merge $to_append --accept theirs-full --allow-mixed-revisions --dry-run");
    }

    public function info($target_folder, $raw = false)
    {
        $cmd = "info $target_folder";
        if (! $raw) {
            $cmd .= ' --xml';
        }

        $xml = $this->exec($target_folder, $cmd);

        if ($raw) {
            return $xml;
        }

        $xml = simplexml_load_string($xml);

        $cur_node = $xml->entry;
        $result = [];
        $stack = [
            [$cur_node, &$result]
        ];

        while (! empty($stack)) {
            $stack_item = array_pop($stack);
            $cur_node = $stack_item[0];
            $output = &$stack_item[1];

            $node_name = $cur_node->getName();
            $node_children = $cur_node->children();

            if (empty($node_children)) {
                $value = sprintf('%s', $cur_node);
                $value = is_numeric($value) ? float($value) : $value;
                $output[ $node_name ] = $value;
                continue;
            } else {
                $output[ $node_name ] = [];

                foreach ($node_children as $node_child) {
                    $stack[] = [$node_child, &$output[ $node_name ]];
                }
            }
        }

        $result = !empty($result['entry']) ? $result['entry'] : [];
        return $result;
    }

    public function getRevision($target_folder)
    {
        $info = $this->info($target_folder, true);

        if (! empty($info)) {
            preg_match('/(.*Rev:\s+)(.*)/', $info, $matches);
            return $matches[2];
        }

        return 0;
    }

    public function checkoutBranch($target_folder, $branch)
    {
        return $this->exec($target_folder, "--non-interactive switch $branch $target_folder");
    }

    public function upgrade($target_folder, $branch)
    {
        $this->revert($target_folder);
        return $this->checkoutBranch($target_folder, $branch);
    }

    public function update($target_folder, $branch)
    {
        $info = $this->info($target_folder);
        $root = $info['repository']['root'];
        $url = $info['url'];
        $branchUrl = $root . "/" . $branch;

        if ($root != $this->repository_url) {
            error("Trying to upgrade '{$this->repository_url}' to different repository: {$root}");
            return false;
        }

        $conflicts = $this->merge($target_folder, 'BASE:HEAD');

        if (strlen(trim($conflicts)) > 0 &&
            preg_match('/conflicts:/i', $conflicts)) {
            echo "SVN MERGE: $conflicts\n";

            if ('yes' == strtolower(promptUser(
                'It seems there are some conflicts. Type "yes" to exit and solve manually or "no" to discard changes. Exit?',
                INTERACTIVE ? 'yes' : 'no',
                array('yes', 'no')
            ))) {
                exit;
            }
        }

        if (! $this->access->fileExists($target_folder . self::SVN_TEMP_FOLDER_PATH)) {
            $path = $this->access->getInterpreterPath($this);
            $script = sprintf("mkdir('%s', 0777, true);", $target_folder . self::SVN_TEMP_FOLDER_PATH);
            $this->access->createCommand($path, ["-r {$script}"])->run();
        }

        if ($this->isUpgrade($url, $branch)) {
            info("Upgrading to '{$branch}'");
            $this->revert($target_folder);
            $this->upgrade($target_folder, $branchUrl);
        } else {
            info("Updating '{$branch}'");
            $this->revert($target_folder);
            $this->exec($target_folder, "update $target_folder --accept theirs-full --force");
        }

        $this->cleanup($target_folder);
    }
}
