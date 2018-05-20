<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

header("HTTP/1.1 503 Service Unavailable", true, 503);
header("Retry-After: 3600");

$timestamp = filemtime(__FILE__); // seconds
$date = date('Y-m-d H:i:s P', $timestamp);

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
    <head>
        <title>Maintenance in progress</title>
    </head>
    <body>
        <h1>Maintenance in progress</h1>
        <p>
            An update started on <b><?= $date; ?></b>
            and it is currently in progress on this website. Please try again in
            a few minutes.
        </p>
    </body>
</html>
