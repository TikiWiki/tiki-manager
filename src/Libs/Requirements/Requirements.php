<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\Requirements;

use PDO;

/**
 * Class Requirements
 * Logic related to Tiki-manager requirements should be placed here
 */
abstract class Requirements
{
    // Each child class should override this property
    const REQUIREMENTS = [];

    protected static $instance;

    public static function getInstance()
    {
        if (!self::$instance) {
            if (isWindows()) {
                self::$instance = new WindowsRequirements();
            } else {
                self::$instance = new LinuxRequirements();
            }
        }

        return self::$instance;
    }

    /**
     * Check single requirement, it will perform an automatic OS check
     * @param $requirementKey
     * @return bool
     */
    public function check($requirementKey)
    {
        // Handle common requirements, the method should be declared in this class
        if (method_exists(__CLASS__, $requirementKey)) {
            return static::$requirementKey();
        }

        $requirements = static::REQUIREMENTS;

        if (empty($requirements[$requirementKey]['commands'])) {
            return true; // NOT A REQUIREMENT
        }

        foreach ($requirements[$requirementKey]['commands'] as $command) {
            if (!$this->hasDependency($command)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a generic error message for a specific requirement
     * @param $requirementKey
     * @return string
     */
    public function getRequirementMessage($requirementKey)
    {
        $requirements = static::REQUIREMENTS;

        $name = $requirements[$requirementKey]['name'];
        $tags = $requirements[$requirementKey]['tags'];

        return "$name not detected. Please make sure $tags is/are installed properly.";
    }

    /**
     * Get a specific requirement tags
     * @param $requirementKey
     * @return mixed
     */
    public static function getTags($requirementKey)
    {
        return static::REQUIREMENTS[$requirementKey]['tags'];
    }

    /**
     * Common function used to check if PHP Sqlite drivers are installed
     * @return bool
     */
    private static function PHPSqlite()
    {
        return in_array('sqlite', PDO::getAvailableDrivers());
    }

    /**
     * Check if OS has a specific dependency
     * @param $command
     * @return bool
     */
    public function hasDependency($command)
    {
        return !empty($this->getDependencyPath($command));
    }

    /**
     * Abstract function to fetch the dependency path based on OS
     * @param $command
     * @return mixed
     */
    abstract public function getDependencyPath($command);

    public function getRequirements()
    {
        return static::REQUIREMENTS;
    }
}
