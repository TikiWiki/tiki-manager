<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application\Tiki\Versions;

use TikiManager\Application\Exception\BadValueException;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;

class TikiRequirements
{
    private $name;
    private $version;
    /**
     * @var SoftwareRequirement
     */
    private $phpVersion;
    /**
     * @var SoftwareRequirement
     */
    private $mysqlVersion;
    /**
     * @var SoftwareRequirement
     */
    private $mariaDBVersion;

    /**
     * @param string $name Name of the version
     * @param string $version version
     * @param SoftwareRequirement $phpVersion PHP version constraints
     * @param SoftwareRequirement $mysqlVersion MySql version constraints
     * @param SoftwareRequirement $mariaDBVersion MariaDB version constraints
     * @throws BadValueException
     */
    public function __construct(string $name, string $version, SoftwareRequirement $phpVersion, SoftwareRequirement $mysqlVersion, SoftwareRequirement $mariaDBVersion)
    {
        // validate version, as all other parameters are validate by type
        // Versions in tiki: branch(26.x): 26, tag(26.1): 26.1, branch(master): master
        if (! preg_match('/(?:\d+(?:\.\d+)?|master)/', $version)) {
            throw new BadValueException('Value of version (' . $version . ') is not valid for TikiRequirements');
        }

        $this->name = $name;
        $this->version = $version;
        $this->phpVersion = $phpVersion;
        $this->mysqlVersion = $mysqlVersion;
        $this->mariaDBVersion = $mariaDBVersion;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return SoftwareRequirement
     */
    public function getPhpVersion(): SoftwareRequirement
    {
        return $this->phpVersion;
    }

    /**
     * @return SoftwareRequirement
     */
    public function getMysqlVersion(): SoftwareRequirement
    {
        return $this->mysqlVersion;
    }

    /**
     * @return SoftwareRequirement
     */
    public function getMariaDBVersion(): SoftwareRequirement
    {
        return $this->mariaDBVersion;
    }

    public function checkRequirements(Instance $instance, bool $ignoreMaxVersion = false): bool
    {
        $phpVersion = CommandHelper::formatPhpVersion($instance->phpversion);

        return $this->phpVersion->isValidVersion($phpVersion, $ignoreMaxVersion);
    }
}
