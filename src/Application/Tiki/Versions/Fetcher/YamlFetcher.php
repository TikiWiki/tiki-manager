<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application\Tiki\Versions\Fetcher;

use Symfony\Component\Yaml\Yaml;
use TikiManager\Application\Tiki\Versions\SoftwareRequirement;
use TikiManager\Application\Tiki\Versions\TikiRequirements;
use TikiManager\Config\Environment;

class YamlFetcher implements RequirementsFetcher
{
    /**
     * @var string Path to the requirements file
     */
    private $requirementsFile;

    public function __construct($requirementsFile = null)
    {
        if (!empty($requirementsFile)) {
            $this->requirementsFile = $requirementsFile;
        } else {
            $this->requirementsFile = Environment::get('CONFIG_FOLDER'). DS . '/tiki_requirements.yml';
        }
    }

    public function getRequirements(): array
    {
        return array_map(function ($req) {
            return new TikiRequirements(
                $req['name'],
                $req['version'],
                new SoftwareRequirement($req['php']['min'] ?? '', $req['php']['max'] ?? ''),
                new SoftwareRequirement($req['mysql']['min'] ?? '', $req['mysql']['max'] ?? ''),
                new SoftwareRequirement($req['mariadb']['min'] ?? '', $req['mariadb']['max'] ?? '')
            );
        }, $this->getParsedRequirements());
    }

    /**
     * Get the parsed requirements
     * @return array
     */
    public function getParsedRequirements(): array
    {
        return Yaml::parseFile($this->requirementsFile);
    }
}
