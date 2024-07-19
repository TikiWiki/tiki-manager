<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Application\Tiki\Handler;

use Exception;

class SystemConfigurationFile
{
    protected array $defaultDangerousDirectives = [
        'preference.cookie_domain',
        'preference.fgal_use_dir',
        'preference.gal_batch_dir',
        'preference.gal_use_dir',
        'preference.fallbackBaseUrl',
        'preference.tmpDir',
        'preference.memcache_enabled',
        'preference.memcache_servers',
        'preference.memcache_prefix',
        'preference.redis_enabled',
        'preference.redis_host',
        'preference.redis_prefix',
        'preference.tikimanager_storage_path',
        'preference.unified_elastic_url',
        'preference.unified_elastic_index_prefix',
        'preference.unified_manticore_url',
        'preference.unified_manticore_index_prefix',
    ];

    protected array $dangerousDirectives = [];

    public const ENV_KEY = 'SYSTEM_CONFIG_DANGER_DIRECTIVES';

    /**
     * Constructor, allows to set the directives to use as parameter or env variable.
     * It falls back to use the default list if another is not provided as env or parameter.
     *
     * @param array|null $directives
     */
    public function __construct(?array $directives = null)
    {
        if ($directives !== null) {
            $this->dangerousDirectives = $directives;
        } elseif (! empty($_ENV[self::ENV_KEY])) {
            $directives = array_map('trim', explode(',', $_ENV[self::ENV_KEY]));
            $this->dangerousDirectives = $directives;
        } else {
            $this->dangerousDirectives = $this->defaultDangerousDirectives;
        }
    }

    /**
     * Return the default list of directives that can exist in the system config file, that may cause clobber issues
     * when the system configuration file is restored to a different (from the original) instance.
     * Example: elastic search address and prefix, php session name, etc.
     *
     * @return array with the directive names
     */
    public function getDefaultDangerousDirectives(): array
    {
        return $this->defaultDangerousDirectives;
    }

    /**
     * Return the current list of directives that can exist in the system config file, that may cause clobber issues
     * when the system configuration file is restored to a different (from the original) instance.
     * Example: elastic search address and prefix, php session name, etc.
     *
     * @return array with the directive names
     */
    public function getDangerousDirectives(): array
    {
        return $this->dangerousDirectives;
    }

    /**
     * Allows to override the default list of directives
     *
     * @param array $directives
     * @return void
     */
    public function setDangerousDirectives(array $directives): void
    {
        $this->dangerousDirectives = $directives;
    }

    /**
     * Attempts to read a file and return true if the file contains any (potentially) dangerous directives
     *
     * @return bool return true if a dangerous directive is found
     */
    public function hasDangerousDirectives(string $filename): bool
    {
        // we expect $file to be accessible in the local filesystem, if that's not the case, you should localize it first
        if (! file_exists($filename)) {
            return false;
        }

        $directives = $this->getDangerousDirectives();

        try {
            $hasDirectives = $this->checkDirectivesExistsInIniFile($filename, $directives);
        } catch (Exception $e) {
            $hasDirectives = $this->checkDirectivesExistsInPlainFile($filename, $directives);
        }

        return $hasDirectives;
    }

    /**
     * Parses an INI file and check if any entry match one of the directives
     *
     * @param string $filename
     * @param array $directives
     * @return bool
     * @throws Exception
     */
    protected function checkDirectivesExistsInIniFile(string $filename, array $directives): bool
    {
        // Tiki uses Laminas to load the ini file, that uses parse_ini_{file,string} under the hood,
        // this will avoid false positives in comments, etc

        if ('.ini.php' === substr($filename, -8)) { // handle .ini.php files
            $initString = $this->retrieveIniPhpFileContents($filename);
            $ini = parse_ini_string($initString, true, INI_SCANNER_NORMAL);
        } else { // handle .ini files (actually assume any other file name will be an ini file)
            $ini = parse_ini_file($filename, true, INI_SCANNER_NORMAL);
        }

        if ($ini === false) {
            throw new Exception('Ini file could not be parsed.');
        }

        $hasDirectives = false;
        array_walk_recursive($ini, function ($item, $key) use ($directives, &$hasDirectives) {
            if (in_array($key, $directives)) {
                $hasDirectives = true;
            }
        });

        return $hasDirectives;
    }

    /**
     * Retrieves INI information from an .ini.php file
     *
     * @see https://gitlab.com/tikiwiki/tiki/-/blob/92228a15b5e9053e17abeaee029ca014fcf81b9a/db/tiki-db.php#L114-125
     * @param string $filename
     * @return false|string
     */
    protected function retrieveIniPhpFileContents(string $filename)
    {
        ob_start();
        include($filename);
        $systemConfigurationFileContent = ob_get_contents();
        ob_end_clean();

        return $systemConfigurationFileContent;
    }

    /**
     * Treat the file as plain text, and search the directive text anywhere in the file
     *
     * @param string $filename
     * @param array $directives
     * @return bool
     */
    protected function checkDirectivesExistsInPlainFile(string $filename, array $directives): bool
    {
        $content = file_get_contents($filename);
        if ($content === false) {
            return false;
        }

        foreach ($directives as $directive) {
            if (strpos($content, $directive) !== false) {
                return true;
            }
        }

        return false;
    }
}
