<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
    $_SERVER['argv'] = $_GET;
}

if ($root = $_SERVER['argv'][1]) {
    chdir($root);
}

$local_php = 'db/local.php';
if (file_exists('installer/shell.php') && !file_exists('console.php')) {
    require_once('installer/installlib.php');
    include_once('lib/adodb/adodb.inc.php');

    include $local_php;
    $dbTiki = ADONewConnection($db_tiki);
    $dbTiki->Connect($host_tiki, $user_tiki, $pass_tiki, $dbs_tiki);

    $installer = new Installer;
    $installer->update();
} else {
    if (!is_dir('db')) {
        fwrite(STDERR, getcwd() . "/db/ directory not found");
        exit(1);
    }
    $command = PHP_BINARY . ' -d memory_limit=256M console.php -n database:update';

    $match = null;
    $db_files = glob('db/*local.php');
    foreach ($db_files as $file) {
        $retval = -1;
        $retstr = '';
        $args = '';

        if (! preg_match(',^db/([a-z0-9_.-]+)?local.php$,', $file, $match)) {
            continue;
        }

        if (count($match) === 3) {
            $args .=  " --site=" . $match[2];
        }

        $retstr = system($command . $args, $retval);
        if ($retval === 0) {
            echo $retstr;
        } else {
            fwrite(STDERR, $retstr . "\n");
        }
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
