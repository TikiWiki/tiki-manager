<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Hooks;

use TikiManager\Application\Instance;

class InstancePatchApplyHook extends TikiCommandHook
{
    public function registerPostHookVars(array $vars)
    {
        parent::registerPostHookVars($vars);

        $this->postHookVars['PATCH_PACKAGE'] = $vars['package'] ?? ($this->postHookVars['PATCH_PACKAGE'] ?? null);
        $this->postHookVars['PATCH_URL'] = $vars['url'] ?? ($this->postHookVars['PATCH_URL'] ?? null);

        $instance = $vars['instance'] ?? null;

        if (!$instance instanceof Instance) {
            return;
        }

        $this->postHookVars['INSTANCE_BACKUP_FILE_' . $instance->id] = $vars['backup_file'] ?? null;
    }
}
