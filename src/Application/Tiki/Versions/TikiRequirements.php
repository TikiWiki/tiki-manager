<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application\Tiki\Versions;

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

    public function __construct($name, $version, $phpVersion, $mysqlVersion, $mariaDBVersion)
    {
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

    public function checkRequirements(Instance $instance): bool
    {
        $phpVersion = CommandHelper::formatPhpVersion($instance->phpversion);

        return $this->phpVersion->isValidVersion($phpVersion);
    }
}
