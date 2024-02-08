<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Hooks;

use TikiManager\Application\Instance;
use TikiManager\Application\Version;

class InstanceCloneHook extends TikiCommandHook
{
    public function registerPostHookVars(array $vars)
    {
        parent::registerPostHookVars($vars);

        $sourceInstance = $vars['source'] ?? null;

        if (!$sourceInstance instanceof Instance) {
            return;
        }

        parent::registerPostInstanceVars($sourceInstance);
        $this->postHookVars['SOURCE_INSTANCE_ID'] = $sourceInstance->id;
        $this->postHookVars['SOURCE_INSTANCE_BACKUP'] = $vars['backup'] ?? null;
    }
}
