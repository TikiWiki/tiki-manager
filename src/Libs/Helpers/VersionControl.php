<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\Helpers;

use TikiManager\Application\Exception\VcsException;

class VersionControl
{

    public static function formatBranch($branch, $vcs = null)
    {
        $vcs = strtolower($vcs ?? $_ENV['DEFAULT_VCS']);

        if ($vcs == 'git') {
            return static::formatGitBranch($branch);
        }

        return $branch;
    }

    protected static function formatGitBranch($branch)
    {
        if (preg_match('/^branches\/\d+\.(\d+|x)$/', $branch)) {
            return str_replace('branches/', '', $branch);
        }

        return $branch;
    }
}
