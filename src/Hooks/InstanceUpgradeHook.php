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
}
