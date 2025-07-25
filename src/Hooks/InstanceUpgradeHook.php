<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Hooks;

use TikiManager\Application\Instance;

class InstanceUpgradeHook extends TikiCommandHook
{
    public function registerPostHookVars(array $vars)
    {
        parent::registerPostHookVars($vars);

        $instance = $vars['instance'] ?? null;

        if (!$instance instanceof Instance) {
            return;
        }

        $this->postHookVars['INSTANCE_PREVIOUS_BRANCH_' . $instance->id] = $vars['previous_branch'] ?? null;
    }

    public function registerFailHookVars(array $vars)
    {
        $instance = $vars['instance'] ?? null;
        $previousBranch = $vars['previous_branch'] ?? null;

        if ($instance instanceof Instance) {
            $this->failHookVars['INSTANCE_ID'] = $instance->id;
        }

        if ($previousBranch) {
            $this->failHookVars['PREVIOUS_BRANCH'] = $previousBranch;
        }

        parent::registerFailHookVars($vars);
    }
}
