<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Helpers;

class Archive
{
    /**
     * Search for backup files, and delete them
     * if they have at least one week old,
     * keeping for one month the ones
     * created in the first day
     * of the month or on
     * Sunday
     *
     * @param $instanceId
     * @param $instanceName
     */
    public static function performArchiveCleanup($instanceId, $instanceName)
    {
        $backup_directory = "{$instanceId}-{$instanceName}";

        $files = glob($_ENV['ARCHIVE_FOLDER'] . "/$backup_directory" . '/*.tar.bz2');

        foreach ($files as $file) {
            $name = basename($file);

            if (preg_match('/^(\d+)-(.*)_(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})\.tar\.bz2$/', $name, $matches)) {
                list($match, $instanceId, $instanceName , $year, $month, $date, $hour, $minute, $second) = $matches;

                // Preserve one backup per month, the one on the first
                if ($date == '01') {
                    continue;
                }

                $time = mktime((int)$hour, (int)$minute, (int)$second, (int)$month, (int)$date, (int)$year);
                $daysAgo = (time() - $time) / (24 * 3600);

                // Keep all backups on Sunday for a month
                $day = date('D', $time);
                if ($day == 'Sun' && $daysAgo <= 31) {
                    continue;
                }

                // Delete backups after a week
                if ($daysAgo > 7) {
                    unlink($file);
                }
            }
        }
    }
}
