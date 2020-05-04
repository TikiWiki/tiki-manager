<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\Helpers;

use TikiManager\Config\App;

class File
{

    /**
     * @param $file
     * @param $target
     * @return bool
     */
    public static function unarchive($file, $target)
    {
        $io = App::get('io');

        if (preg_match('/(.*)\.(tar\.bz2|zip|7z|tar\.gz)/', basename($file), $matches)) {
            $name = $matches[1];
            $ext = $matches[2];
            switch ($ext) {
                case 'tar.bz2':
                    $command = sprintf('tar -xvjf %s -C %s --strip-components 1 1> /dev/null', $file, $target);
                    break;
                case 'tar.gz':
                    $command = sprintf('tar -xzf %s -C %s --strip-components 1 1> /dev/null', $file, $target);
                    $output = shell_exec($command);
                    if ($output) {
                        $io->error($output);
                    } else {
                        return true;
                    }
                    break;
                case 'zip':
                    if (!file_exists($target)) {
                        mkdir($target);
                    }
                    shell_exec(sprintf('unzip -d "%s" "%s" 1> /dev/null ', $target, $file));
                    $list = scandir($target);
                    $extractedFolders = array_values(array_map(function ($d) use ($target) {
                        return $target . DIRECTORY_SEPARATOR . $d;
                    }, array_filter($list, function ($d) use ($target) {
                        return $d !== '.' && $d !== '..' && is_dir($target . DIRECTORY_SEPARATOR . $d);
                    })));
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
                    break;
                case '7z':
                    $command = sprintf(
                        '7za x "%s" -o"%s" 1> /dev/null && mv "%s/%s"/* "%s" 1> /dev/null',
                        $file,
                        $target,
                        $target,
                        $name,
                        $target
                    );
                    break;
                default:
                    return false;
            }
            if ($command) {
                if (!file_exists($target)) {
                    mkdir($target);
                }
                $output = shell_exec($command);
                if ($output) {
                    $io->error($output);
                } else {
                    return true;
                }

                return true;
            }
        }
        return false;
    }

    /**
     * @param $url
     * @param $target
     * @return string|false The $targetPath if successful, false otherwise
     * @throws \Exception
     */
    public static function download($url, $target)
    {
        $previousException = null;

        try {
            $downloadedFileContents = file_get_contents($url);
        } catch (\Exception $e) {
            $downloadedFileContents = false;
            $previousException = $e;
        }

        if ($downloadedFileContents === false) {
            throw new \Exception('Failed to download file at: ' . $url, 0, $previousException);
        }

        return file_put_contents($target, $downloadedFileContents) ? $target : false;
    }
}
