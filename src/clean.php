<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

function perform_archive_cleanup($instance_id, $instance_name)
{
    $backup_directory = "{$instance_id}-{$instance_name}";

    $files = glob(ARCHIVE_FOLDER . "/$backup_directory" . '/*.tar.bz2');

    foreach ($files as $file) {

        $name = basename($file);

        if (preg_match('/^(\d+)-(.*)_(\d{4})-(\d{2})-(\d{2})_(\d{2})-(\d{2})-(\d{2})\.tar\.bz2$/', $name, $matches)) {

            list($match, $instance_id, $instance_name , $year, $month, $date, $hour, $minute, $second) = $matches;

            // Preserve one backup per month, the one on the first
            if ($date == '01') continue;

            $time = mktime((int)$hour, (int)$minute, (int)$second, (int)$month, (int)$date, (int)$year);
            $daysAgo = (time() - $time) / (24 * 3600);

            // Keep all backups on Sunday for a month
            $day = date('D', $time);
            if ($day == 'Sun' && $daysAgo <= 31) continue;

            // Delete backups after a week
            if ($daysAgo > 7) unlink($file);
        }
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
