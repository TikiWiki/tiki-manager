<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Helpers;

use Symfony\Component\Filesystem\Filesystem;

class Files
{

    /**
     * Compare files content
     *
     * @param string $file1
     * @param string $file2
     * @return array
     */
    public static function compareFiles($file1, $file2)
    {
        $diffFile = [];
        $fileSystem = new Filesystem();
        if ($fileSystem->exists($file1) && $fileSystem->exists($file2)) {
            $readFile1 = file($file1);
            $readFile2 = file($file2);
            unset($readFile1[count($readFile1) - 1]);
            unset($readFile2[count($readFile2) - 1]);
            $diffFile = array_diff($readFile1, $readFile2);
        }

        return $diffFile;
    }
}
