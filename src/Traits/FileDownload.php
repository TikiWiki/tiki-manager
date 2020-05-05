<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Traits;

trait FileDownload
{
    /**
     * @param $url
     * @param $target
     * @return string|false The $targetPath if successful, false otherwise
     * @throws \Exception
     */
    public function download($url, $target)
    {
        $previousException = null;

        try {
            $fileContents = file_get_contents($url);
        } catch (\Exception $e) {
            $fileContents = false;
            $previousException = $e;
        }

        if ($fileContents === false) {
            throw new \Exception('Failed to download file at: ' . $url, 0, $previousException);
        }

        return file_put_contents($target, $fileContents) ? $target : false;
    }
}
