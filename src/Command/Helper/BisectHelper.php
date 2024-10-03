<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command\Helper;

use TikiManager\Application\Instance;
use TikiManager\Config\App;

class BisectHelper
{
    private $instance;
    private $vcs_instance;
    private $io;
    private $session;

    public function __construct(Instance $instance)
    {
        $this->instance = $instance;
        $this->vcs_instance = $instance->getVersionControlSystem();
        $this->io = App::get('io');
        $this->session = $instance->getOnGoingBisectSession();
    }

    public function initializeAndStartSession($badCommit, $goodCommit)
    {
        try {
            $goodCommit = $goodCommit ?: $this->vcs_instance->getRevision($this->instance->webroot);
            $output = $this->vcs_instance->startBisect($this->instance->webroot, $badCommit, $goodCommit);
            $this->io->info("Bisect Output: \n" . $output);
            $this->instance->updateOrSaveBisectSession([
                ':instance_id' => $this->instance->id,
                ':bad_commit' => $badCommit,
                ':good_commit' => $goodCommit,
                ':current_commit' => $this->vcs_instance->getRevision($this->instance->webroot),
                ':status' => 'in_progress'
            ]);
        } catch (\Exception $e) {
            $message = sprintf("An error occurred while initializing bisect session:\n%s", $e->getMessage());
            $this->io->error($message);
            return false;
        }
    }

    public function markCommitAsGood($commitId)
    {
        try {
            $commitId = $commitId ?: $this->vcs_instance->getRevision($this->instance->webroot);
            $output = $this->vcs_instance->markGoodBisect($this->instance->webroot, $commitId);
            $this->io->info("Commit Marked As Good: \n" . $output);
            $this->instance->updateOrSaveBisectSession([
                ':good_commit' => $commitId,
                ':instance_id' => $this->instance->id,
                ':status' => 'in_progress',
                ':bad_commit' => $this->session->bad_commit,
                ':current_commit' => $this->vcs_instance->getRevision($this->instance->webroot)
            ]);
        } catch (\Exception $e) {
            $message = sprintf("An error occurred while marking commit as good:\n%s", $e->getMessage());
            $this->io->error($message);
            return false;
        }
    }

    public function markCommitAsBad($commitId)
    {
        try {
            $commitId = $commitId ?: $this->vcs_instance->getRevision($this->instance->webroot);
            $output = $this->vcs_instance->markBadBisect($this->instance->webroot, $commitId);
            $this->io->info("Commit Marked As Bad: \n" . $output);
            $this->instance->updateOrSaveBisectSession([
                ':bad_commit' => $commitId,
                ':instance_id' => $this->instance->id,
                ':status' => 'in_progress',
                ':good_commit' => $this->session->good_commit,
                ':current_commit' => $this->vcs_instance->getRevision($this->instance->webroot)
            ]);
        } catch (\Exception $e) {
            $message = sprintf("An error occurred while marking commit as bad:\n%s", $e->getMessage());
            $this->io->error($message);
            return false;
        }
    }

    public function finishBisectSession()
    {
        try {
            $output = $this->vcs_instance->resetBisect($this->instance->webroot);
            $this->io->info("Bisect Session Finished: \n" . $output);
            $this->instance->updateOrSaveBisectSession([
                ':good_commit' => $this->session->good_commit,
                ':bad_commit' => $this->session->bad_commit,
                ':instance_id' => $this->instance->id,
                ':status' => 'completed',
                ':current_commit' => $this->vcs_instance->getRevision($this->instance->webroot)
            ]);
        } catch (\Exception $e) {
            $message = sprintf("An error occurred while finishing bisect session:\n%s", $e->getMessage());
            $this->io->error($message);
            return false;
        }
    }
}
