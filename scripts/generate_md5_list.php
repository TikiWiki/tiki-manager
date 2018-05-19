<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

if(!function_exists('calculate_forder_checksum')) {
    function calculate_folder_checksum($folder, $callback=null)
    {
        $result = array();

        if(!is_callable($callback)) {
            $callback = function($hash, $filename) use (&$result) {
                return array($hash, $filename);
            };
        }

        $diriterator = new RecursiveDirectoryIterator($folder);
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
                $callback(md5_file($name), $name);
            }
        }
        return $result;
    }
}

if ( realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    call_user_func(function () {
        $cur = getcwd();
        if (array_key_exists('REQUEST_METHOD', $_SERVER))
            $next = $_GET[1];

        elseif (count($_SERVER['argv']) > 1)
            $next = $_SERVER['argv'][1];

        if (isset($next) && file_exists($next))
            chdir($next);

        $callback = function($md5, $filename){ printf("%s:%s\n", $md5, $filename); };
        calculate_folder_checksum('.', $callback);
        chdir($cur);
    });
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
