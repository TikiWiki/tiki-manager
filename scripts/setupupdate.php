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

if(! in_array($hour, range(0, 23)))
    die(error('Invalid hour.'));
if(! in_array($minute, range(0, 59)))
    die(error('Invalid minute.'));

$path = realpath( dirname(__FILE__) . '/update.php' );
$entry = sprintf(
    "%d %d * * * %s -d memory_limit=256M %s auto %s\n",
    $minute, $hour, php(), $path, $which
);

warning("NOTE: Only CVS and SVN instances can be updated.\n");
echo "Which instances do you want to update?\n";

$instances = Instance::getUpdatableInstances();
printInstances($instances);

$which = promptUser('>>> ');

file_put_contents($file = TEMP_FOLDER . '/crontab', `crontab -l` . $entry);

echo "If adding to crontab fails and blocks, hit Ctrl-C and add these parameters manually.\n";
echo "\t$entry";

`crontab $file`;

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
