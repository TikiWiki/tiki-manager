<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';

$time = promptUser('What time should it run at?', '00:00');

list($hour, $minute) = explode(':', $time);

$hour = (int)$hour;
$minute = (int)$minute;
$options = "";

if (! in_array($hour, range(0, 23)))
    die(error('Invalid hour.'));
if (! in_array($minute, range(0, 59)))
    die(error('Invalid minute.'));

$excludeInstances = promptUser('Do you want to exclude any instance from backups?', '', ['y', 'n']);

if ($excludeInstances == 'y') {
    $instances = Instance::getInstances(true);
    $selection = selectInstances($instances, "Which instances do you want to exclude?\n");

    if (!empty($selection) && is_array($selection)) {
        $selection = array_map(function($instance) { return $instance->getId(); }, $selection);
        $selection = implode(',', $selection);
        $options .= "--exclude=$selection ";
    }
}

$path = 'scripts/backup.php';
$trimpath = realpath(dirname(__FILE__) . '/..');
$entry = sprintf(
    "%d %d * * * cd %s && %s -d memory_limit=256M %s all %s\n",
    $minute, $hour, $trimpath, php(), $path, $options);

file_put_contents($file = TEMP_FOLDER . '/crontab', `crontab -l` . $entry);

echo "\nIf adding to crontab fails and blocks, hit Ctrl-C and add these parameters manually.\n";
echo "\t$entry\n";

`crontab $file`;

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
