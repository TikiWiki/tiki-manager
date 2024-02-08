<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Hooks;

use TikiManager\Application\Instance;

class InstanceSetupschedulercronHook extends TikiCommandHook
{
    public function registerPostHookVars(array $vars)
    {
        parent::registerPostHookVars($vars);

        $instance = $vars['instance'] ?? null;

        if (!$instance instanceof Instance) {
            return;
        }

        $cron = $vars['scheduler_cron'] ?? null;
        if ($cron instanceof Instance\CronJob) {
            $instanceId = $instance->id;
            $this->postHookVars['INSTANCE_JOB_ENABLED_' . $instanceId] = $cron->isEnabled();
            $this->postHookVars['INSTANCE_JOB_TIME_' . $instanceId] = $cron->getTime();
            $this->postHookVars['INSTANCE_JOB_COMMAND_' . $instanceId] = $cron->getCommand();
        }
    }
}
