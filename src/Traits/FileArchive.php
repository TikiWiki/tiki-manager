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

        $output = shell_exec($command);
        if ($output) {
            App::get('io')->error($output);
        }

        return true;
    }

    protected function extractTarGz($file, $target)
    {
        $command = sprintf('tar -xzf %s -C %s --strip-components 1 1> /dev/null', $file, $target);

        $output = shell_exec($command);
        if ($output) {
            App::get('io')->error('output');
        }

        return true;
    }

    protected function extractZip($file, $target)
    {
        shell_exec(sprintf('unzip -d "%s" "%s" 1> /dev/null ', $target, $file));

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

        $output = shell_exec($command);
        if ($output) {
            App::get('io')->error('output');
        }

        return true;
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

        return shell_exec($command);
    }
}
