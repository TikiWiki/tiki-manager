<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Manager\Update;

use Exception;
use Gitonomy\Git\Exception\ReferenceNotFoundException;
use Gitonomy\Git\Reference\Branch;
use Gitonomy\Git\Repository;
use TikiManager\Manager\UpdateManager;

class Git extends UpdateManager
{
    /** @var Repository */
    protected $repository;

    public function __construct($targetFolder)
    {
        if (!$targetFolder instanceof Repository) {
            $targetFolder = new Repository($targetFolder);
        }

        $this->repository = $targetFolder;
        parent::__construct($targetFolder->getWorkingDir());
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function update()
    {
        $this->repository->run('pull');
        $this->repository->getReferences(true);

        $this->runComposerInstall();
    }

    public function fetch()
    {
        $this->repository->run('fetch', ['--all']);
        $this->repository->getReferences(true);
    }

    public function getBranchName()
    {
        $ref = $this->repository->getHead();
        return $ref instanceof Branch ? $ref->getName() : null;
    }

    /**
     * @inheritDoc
     */
    public function getCurrentVersion()
    {
        $commit = $this->repository->getHeadCommit();
        return [
            'version' => $commit->getShortHash(),
            'date' => $commit->getAuthorDate()->format(DATE_RFC3339_EXTENDED)
        ];
    }

    /**
     * @inheritDoc
     */
    public function info()
    {
        $info = parent::info();
        $info .= sprintf("\nBranch: %s", $this->getBranchName() ?? '');

        return $info;
    }

    public function getType()
    {
        return 'Git';
    }

    public function getRemoteVersion($branch = null)
    {
        // Update remote references
        $this->fetch();
        $branch = $branch ?? $this->getBranchName();

        if (empty($branch)) {
            return false;
        }

        $remotes = $this->repository->run('remote');
        $remotes = trim($remotes) ? explode("\n", trim($remotes)): [];

        foreach ($remotes as $remote) {
            $name = $remote . '/' . $branch;
            try {
                $remoteBranch = $this->repository->getReferences()->getRemoteBranch($name);
                $commit = $remoteBranch->getCommit();
                return [
                    'version' => $commit->getShortHash(),
                    'date' => $commit->getAuthorDate()->format(DATE_RFC3339_EXTENDED)
                ];
            } catch (ReferenceNotFoundException $e) {
                continue;
            }
        }

        return false;
    }
}
