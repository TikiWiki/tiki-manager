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
use TikiManager\Manager\Update\Exception\TrackingInformationNotFoundException;
use TikiManager\Manager\UpdateManager;

class Git extends UpdateManager
{
    /** @var Repository */
    protected $repository;

    public function __construct($targetFolder)
    {
        if (!$targetFolder instanceof Repository) {
            $targetFolder = new Repository($targetFolder, ['debug' => false]);
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
            'date' => $commit->getCommitterDate()->format(DATE_RFC3339_EXTENDED)
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

    /**
     * @return string
     * @throws TrackingInformationNotFoundException
     */
    public function getUpstreamBranch()
    {
        $upstream = trim($this->repository->run('rev-parse', ['--abbrev-ref', '@{upstream}']));

        if (!$upstream) {
            $branchName = $this->getBranchName();
            throw new TrackingInformationNotFoundException($branchName);
        }

        return $upstream;
    }

    /**
     * @param null $branch
     * @return array|false
     * @throws TrackingInformationNotFoundException
     */
    public function getRemoteVersion($branch = null)
    {
        // Update remote references
        $this->fetch();
        $branch = $branch ?? $this->getUpstreamBranch();

        if (empty($branch)) {
            return false;
        }

        try {
            $remoteBranch = $this->repository->getReferences()->getRemoteBranch($branch);
            $commit = $remoteBranch->getCommit();
            return [
                'version' => $commit->getShortHash(),
                'date' => $commit->getCommitterDate()->format(DATE_RFC3339_EXTENDED)
            ];
        } catch (ReferenceNotFoundException $e) {

        }

        return false;
    }

    /**
     * @return bool
     */
    public function isHeadDetached() {
        return $this->repository->isHeadDetached();
    }
}
