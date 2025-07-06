<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Hooks;

use TikiManager\Application\Instance;

class InstanceCloneAndRedactHook extends TikiCommandHook
{
    public function registerFailHookVars(array $vars)
    {
        $instance = $vars['instance'] ?? null;

        if ($instance instanceof Instance) {
            $this->failHookVars['INSTANCE_ID'] = $instance->id;
        }

        parent::registerFailHookVars($vars);
    }
}
