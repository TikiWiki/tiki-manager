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
        if ($targetFolder instanceof Repository) {
            $this->repository = $targetFolder;
            $targetFolder = $targetFolder->getWorkingDir();
        } else {
            $this->repository = new Repository($targetFolder);
        }

        parent::__construct($targetFolder);
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
            'date' => $commit->getCommitterDate()->format(DATE_RFC3339_EXTENDED)
        ];
    }

    /**
     * @inheritDoc
     */
    public function info()
    {
        $info = parent::info();
        return sprintf("%s\nBranch: %s", $info, $this->getBranchName());
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

        $remotes = $this->repository->run('remote');

        if (empty($remotes)) {
            return false;
        }

        $remotes = explode("\n", $remotes);
        foreach ($remotes as $remote) {
            $name = $remote . '/' . $branch;
            try {
                $remoteBranch = $this->repository->getReferences()->getRemoteBranch($name);
                $commit = $remoteBranch->getCommit();
                return [
                    'version' => $commit->getShortHash(),
                    'date' => $commit->getCommitterDate()->format(DATE_RFC3339_EXTENDED)
                ];
            } catch (ReferenceNotFoundException $e) {
                continue;
            }
        }

        return false;
    }
}
