<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Repository;

class SVN
{
    private $access;
    private $repository;
    private $svn_command = 'svn';
    private $svn_global_args = [
        '--non-interactive',
    ];

    private $svn_default_args = [
        'info' => [
            '--xml'
        ],
        'update' => [
            '--accept theirs-full',
            '--force',
        ],
        'upgrade' => [
            '--force',
            '--accept theirs-full',
        ]
    ];

    public function __construct($repository, $access)
    {
        $this->repository = $repository;
        $this->access = $access;
    }

    private function execute($subcommand, $args)
    {
        $command = [$this->svn_command];
        $command = array_merge($command, $this->svn_global_args);
        $command[] = $subcommand;

        foreach ($args as $arg) {
            $command[] = strpos($arg, '-') === 0
                ? $arg
                : escapeshellarg($arg);
        }

        $command = join(' ', $command);
        $result = $this->access->shellExec($command, true);
        return $result;
    }

    public function getBranchUrl($branch)
    {
        return "{$this->repository}/$branch";
    }

    public function getDefaultArgs($subcommand)
    {
        if (!empty($this->svn_default_args[$subcommand])) {
            $result = $this->svn_default_args[$subcommand];
            return $result;
        }
        return [];
    }

    public function getRepositoryBranch($path)
    {
        $info = $this->info($path);
        $url = $info['url'];
        $root = $info['repository']['root'];
        $branch_index = strlen($root);
        $branch_name = substr($url, $branch_index);
        $branch_name = trim($branch_name, '/');
        return $branch_name;
    }

    public function isUpgrade($current, $branch)
    {
        $branch = $this->getBranchUrl($branch);
        $is_upgrade = $current !== $branch;
        return $is_upgrade;
    }

    public function merge($path, $branch, $args = [])
    {
        if (empty($args)) {
            $args = $this->getDefaultArgs('merge');
        }

        if (preg_match('/^(\w+):(\w+)$/', $branch)) {
            $args[] = "--revision {$branch}";
        } elseif (is_numeric($branch)) {
            $args[] = "--change {$branch}";
        } else {
            $branch = $this->getBranchUrl($branch);
        }

        $args[] = $path;
        $result = $this->execute('merge', $args);
        return $result;
    }

    public function update($path, $args = [])
    {
        if (empty($args)) {
            $args = $this->getDefaultArgs('update');
        }
        $args[] = $path;
        $result = $this->execute('update', $args);
        return $result;
    }

    public function revert($path, $args)
    {
        if (empty($args)) {
            $args = $this->getDefaultArgs('revert');
        }
        $args[] = $path;
        $result = $this->execute('revert', $args);
        return $result;
    }

    public function svnSwitch($path, $branch, $args = [])
    {
        $branch = $this->getBranchUrl($branch);
        if (empty($args)) {
            $args = $this->getDefaultArgs('switch');
        }
        $args[] = $branch;
        $args[] = $path;
        $result = $this->execute('switch', $args);
        return $result;
    }

    public function upgrade($path, $branch, $args = [])
    {
        if (empty($args)) {
            $args = $this->getDefaultArgs('upgrade');
        }
        $result = $this->revert($path, [
            '--recursive'
        ]);
        $result = $this->svnSwitch($path, $branch, $args);
        return $result;
    }

    public function cleanup($path, $args = [])
    {
        if (empty($args)) {
            $args = $this->getDefaultArgs('cleanup');
        }
        $args[] = $path;
        $result = $this->execute('cleanup', $args);
        return $result;
    }

    public function updateInstanceTo($path, $branch)
    {
        $info = $this->info($path);
        $root = $info['repository']['root'];
        $url = $info['url'];

        if ($root != $this->repository) {
            error("Trying to upgrade '{$this->repository}' to different repository: {$root}");
            return false;
        }

        $conflicts = $this->merge($path, 'BASE:HEAD', [
            '--accept theirs-full',
            '--allow-mixed-revisions',
            '--dry-run',
        ]);

        if (strlen(trim($conflicts)) > 0 &&
            preg_match('/conflicts:/i', $conflicts)) {
            echo "SVN MERGE: $conflicts\n";

            if ('yes' == strtolower(promptUser(
                'It seems there are some conflicts. Type "yes" to exit and solve manually or "no" to discard changes. Exit?',
                $_ENV['INTERACTIVE'] ? 'yes' : 'no',
                ['yes', 'no']
            ))) {
                exit;
            }
        }


        if ($this->isUpgrade($url, $branch)) {
            info("Upgrading to '{$branch}'");
            $this->upgrade($path, $branch);
        } else {
            info("Updating '{$branch}'");
            $this->update($path);
        }

        $this->cleanup($path);
    }

    public function info($path, $args = [])
    {
        if (empty($args)) {
            $args = $this->getDefaultArgs('info');
        }
        $args[] = $path;

        $xml = $this->execute('info', $args);
        $xml = simplexml_load_string($xml);
        if ($xml) {
            $cur_node = $xml->entry;
            $result = [];
            $stack = [
                [$cur_node, &$result]
            ];

            while (!empty($stack)) {
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
        }

        $result = !empty($result['entry']) ? $result['entry'] : [];
        return $result;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
