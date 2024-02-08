<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Hooks;

use TikiManager\Application\Instance;

interface HookInterface
{
    public function getHookName(): string;

    public function registerPreHookVars();

    public function registerPostHookVars(array $vars);
}
