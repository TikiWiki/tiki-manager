<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application\Tiki\Versions;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;

class SoftwareRequirement
{

    /**
     * @var string
     */
    private $min;

    /**
     * @var string
     */
    private $max;

    public function __construct($min, $max = '')
    {
        $this->min = stringfy($min);
        $this->max = stringfy($max);
    }

    /**
     * @return string
     */
    public function getMin(): string
    {
        return $this->min;
    }

    /**
     * @return string
     */
    public function getMax(): string
    {
        return $this->max;
    }

    public function isValidVersion($version, $ignoreMaxVersion = false): bool
    {
        if (empty($this->max) || $ignoreMaxVersion) {
            return Comparator::greaterThanOrEqualTo($version, $this->min);
        }

        $constraint = sprintf('%s - %s', $this->min, $this->max);

        return Semver::satisfies($version, $constraint);
    }
}
