<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Manager\Update\Exception;

class TrackingInformationNotFoundException extends \Exception
{
    public function __construct($branch)
    {
        $message = <<<TXT
There is no tracking information for the current branch.
If you wish to set tracking information for this branch you can do so with:

    git branch --set-upstream-to=<remote>/<branch> {$branch}
TXT;
        parent::__construct($message);
    }
}
