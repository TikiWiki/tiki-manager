<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application\Tiki\Versions;

use TikiManager\Application\Tiki\Versions\Fetcher\RequirementsFetcher;

class TikiRequirementsHelper
{
    private $requirements;

    public function __construct(RequirementsFetcher $fetcher)
    {
        $this->requirements = $fetcher->getRequirements();
    }

    public function findByBranchName($branchName): ?TikiRequirements
    {
        $regex = '/\d{1,2}(\.?\d{1,2})?(\.?\d{1,2})?/';
        preg_match($regex, $branchName, $matches, PREG_OFFSET_CAPTURE, 0);
        if (empty($matches[0])) {
            return $this->requirements[0];
        }
        $tikiVersion = $matches[0][0];
        $supported = array_values(array_filter($this->requirements, function ($requirement) use ($tikiVersion) {
            return version_compare($tikiVersion, $requirement->getVersion(), '>=');
        }));

        if (empty($supported)) {
            return end($this->requirements);
        }
        return $supported[0];
    }
}
