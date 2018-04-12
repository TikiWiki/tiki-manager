<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class RC_SVN
{
    private $repository;

    function __construct($repository)
    {
        $this->repository = $repository;
    }

    function updateInstanceTo(Instance $instance, $path)
    {
        $access = $instance->getBestAccess('scripting');
        if (! $access instanceof ShellPrompt) return false;

        $info = $this->getRepositoryInfo($instance, $access);

        if (isset($info['root']) && $info['root'] != $this->repository)
            return false;


        $full_svn_path = "{$this->repository}/$path";
        $full_svn_path_escaped = escapeshellarg($full_svn_path);

        info("Updating SVN to '{$full_svn_path}'");
        $verification = $access->shellExec(
            array(
                "cd {$instance->webroot} && svn merge --dry-run --revision BASE:HEAD . --allow-mixed-revisions"
            ),
            true
        );

        if (strlen(trim($verification)) > 0 &&
            preg_match('/conflicts:/i', $verification)) {

            echo "SVN MERGE: $verification\n";

            if ('yes' == strtolower(promptUser(
                'It seems there are some conflicts. Type "yes" to exit and solve manually or "no" to discard changes. Exit?',
                INTERACTIVE ? 'yes' : 'no',
                array('yes', 'no') )))
                exit;
        }

        if (! isset($info['url']) || $info['url'] == $full_svn_path)
            $access->shellExec('svn up --non-interactive ' . escapeshellarg($instance->webroot));
        else {
            $access->shellExec(
                'svn up --non-interactive ' . escapeshellarg( $instance->getWebPath('temp')),
                "svn switch --force --accept theirs-full --non-interactive {$full_svn_path_escaped} " . escapeshellarg($instance->webroot)
            );
        }
        $access->shellExec('svn  --non-interactive ' . escapeshellarg($instance->webroot));
    }

    private function getRepositoryInfo($instance, $access)
    {
        $remoteText = $access->shellExec('svn info ' . escapeshellarg($instance->webroot), 'sleep 1');
        if (empty($remoteText)) return array();

        $info = array();
        $raw = explode("\n", $remoteText);
        foreach ($raw as $line) {
            if (! strlen(trim($line)) || ! strpos($line, ':')) continue;

            list($key, $value) = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);

            switch ($key) {
            case 'URL':
                $info['url'] = $value;
                break;
            case 'Repository Root':
                $info['root'] = $value;
                break;
            }
        }

        return $info;
    }

    function getRepositoryBranch(Instance $instance)
    {
        $access = $instance->getBestAccess('scripting');
        if (! $access instanceof ShellPrompt) return false;

        $info = $this->getRepositoryInfo($instance, $access);
        
        if (isset($info['url']))
            return substr($info['url'], strlen($this->repository) + 1);
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
