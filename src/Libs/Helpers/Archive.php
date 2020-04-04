<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\Helpers;

class Archive
{
    /**
     * Search for backup files, and delete them if:
     * - they were created for more than a week;
     * - were created on sunday and they are 31 days old or more.
     * Backups on the first day of the month, on sunday during last 31 days or daily backups for a week are kept.
     * If maxBackups is set, it will reduce the remaining backups to the given number of backups to keep.
     *
     * @param $instanceId
     * @param $instanceName
     * @param int $maxBackups
     */
    public static function cleanup($instanceId, $instanceName, $maxBackups = 0)
    {
        $backup_directory = "{$instanceId}-{$instanceName}";

        $files = glob($_ENV['ARCHIVE_FOLDER'] . "/$backup_directory" . '/*.tar.bz2');

        foreach ($files as $file) {
            $name = basename($file);

            if (preg_match('/^(\d+)-(.*)_(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})\.tar\.bz2$/', $name, $matches)) {
                list($match, $instanceId, $instanceName, $year, $month, $date, $hour, $minute, $second) = $matches;

                // Preserve one backup per month, the one on the first
                if ($date == '01') {
                    continue;
                }

                $time = mktime((int) $hour, (int) $minute, (int) $second, (int) $month, (int) $date, (int) $year);
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

        if ($maxBackups == 0) {
            return;
        }

        $files = glob($_ENV['ARCHIVE_FOLDER'] . "/$backup_directory" . '/*.tar.bz2');
        $countBackups = count($files);

        if ($countBackups <= $maxBackups) {
            return;
        }

        foreach ($files as $file) {
            if ($countBackups > $maxBackups) {
                unlink($file);
                $countBackups--;
            } else {
                break;
            }
        }
    }
}
