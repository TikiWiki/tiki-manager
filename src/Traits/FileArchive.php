<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Traits;

use TikiManager\Config\App;

trait FileArchive
{

    /**
     * @param $file
     * @param $target
     * @return bool
     *
     * @uses extractTarBz2
     * @uses extractTarGz
     * @uses extractZip
     * @uses extract7z
     */
    public function extract($file, $target)
    {
        preg_match('/(tar\.bz2|zip|7z|tar\.gz)$/', basename($file), $matches);

        if (empty($matches[1])) {
            return false;
        }

        if (!file_exists($target)) {
            mkdir($target);
        }

        $method = 'extract' . str_replace(' ', '', ucwords(str_replace('.', ' ', $matches[1])));

        if (!method_exists(__CLASS__, $method)) {
            return false;
        }

        return call_user_func([__CLASS__, $method], $file, $target);
    }

    protected function extractTarBz2($file, $target)
    {
        $command = sprintf('tar -xvjf %s -C %s --strip-components 1 1> /dev/null', $file, $target);

        return $this->runCommand($command);
    }

    protected function extractTarGz($file, $target)
    {
        $command = sprintf('tar -xzf %s -C %s --strip-components 1 1> /dev/null', $file, $target);

        return $this->runCommand($command);
    }

    protected function extractZip($file, $target)
    {
        $command = sprintf('unzip -d "%s" -o "%s" 2>&1 > /dev/null ', $target, $file);

        if (!$this->runCommand($command)) {
            return false;
        }

        $extractedFolders = array_filter(glob($target . '/*'), 'is_dir');

        if (empty($extractedFolders)) {
            return false;
        }

        $command = sprintf(
            'rsync -a "%s/" "%s-tmp" && rm -rf "%s" && rsync -a "%s-tmp/" "%s"  && rm -rf "%s-tmp"',
            $extractedFolders[0],
            $target,
            $target,
            $target,
            $target,
            $target
        );

        return $this->runCommand($command);
    }

    protected function extract7z($file, $target)
    {
        $name = substr($file, -3);
        $command = sprintf(
            '7za x "%s" -o"%s" 1> /dev/null && mv "%s/%s"/* "%s" 1> /dev/null',
            $file,
            $target,
            $target,
            $name,
            $target
        );

        return $this->runCommand($command);
    }

    protected function runCommand($command)
    {
        exec($command, $commandOutput, $exitCode);
        if ($exitCode !== 0) {
            App::get('io')->error(implode(PHP_EOL, $commandOutput));
            return false;
        }

        return true;
    }
}
