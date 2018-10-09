<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

header("HTTP/1.1 503 Service Unavailable", true, 503);
header("Retry-After: 3600");

// Clear the stat cache for this file
clearstatcache(true, __FILE__);
// Create a first DateTime object with current date
$updateTime = date_create();
// Create a second DateTime object to hold the file modification time
$timestamp = date_create();
// Set to the file modification time
date_timestamp_set($timestamp,filemtime(__FILE__));
// Get the differences of both
$interval = date_diff($timestamp, $updateTime);
// Format the display with full date and time of start (timestamp of file)
$dateStart = gmdate ('Y-m-d h:i:s', $timestamp->getTimestamp());
// Format the display with usual hours, minutes, seconds (don't need the day, we hope so !)
$timeElapsed = $interval->format('%hh:%im:%ss');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
    <head>
        <title>Maintenance in progress</title>
    </head>
    <body>
        <h1>Maintenance in progress</h1>
        <p>
            An update started on <b><?= $dateStart; ?><i>UTC</i></b>
            and has progressed for <b><?= $timeElapsed; ?></b>. Please try again in
            a few minutes.
        </p>
    </body>
</html>
