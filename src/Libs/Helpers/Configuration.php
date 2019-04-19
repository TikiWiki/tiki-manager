<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\Helpers;

use Symfony\Component\Yaml\Yaml;

/**
 * Class Configuration
 * All configuration related logic should be placed in this class
 * @package App\libs\helpers
 */
class Configuration
{
    private $configuration = null;

    public function __construct()
    {
        $this->readConfiguration();
    }

    /**
     * Gets configuration file
     * @return null|array
     */
    public function get()
    {
        return $this->configuration;
    }

    /**
     * Reads configuration file
     */
    public function readConfiguration()
    {
        if (! file_exists(CONFIGURATION_FILE_PATH)) {
            return;
        }

        $this->configuration = Yaml::parseFile(CONFIGURATION_FILE_PATH);
    }
}
