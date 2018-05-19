<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

$cur = getcwd();

if (array_key_exists('REQUEST_METHOD', $_SERVER))
    $next = $_GET[1];
elseif (isset($folder))
    $next = $folder;
elseif (count($_SERVER['argv']) > 1)
    $next = $_SERVER['argv'][1];

if (file_exists($next))
    chdir($next);

$diriterator = new RecursiveDirectoryIterator('.');
$objiterator = new RecursiveIteratorIterator(
    $diriterator,
    RecursiveIteratorIterator::SELF_FIRST
);

$ignore_pattern = '#(^\./temp|/\.git|/\.svn)/#';
foreach($objiterator as $name => $object) {
    if (preg_match($ignore_pattern, $name)) {
        continue;
    }

    if ($object->getType() === 'file' && is_readable($name)) {
        printf("%s:%s\n", md5_file($name), $name);
    }
}

chdir($cur);
// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
