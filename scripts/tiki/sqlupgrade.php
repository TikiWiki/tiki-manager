<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET')
    $_SERVER['argv'] = $_GET;

if ($root = $_SERVER['argv'][1])
    chdir($root);

if (file_exists('installer/shell.php')) {
    $local_php = 'db/local.php';

    require_once('installer/installlib.php');
    include_once('lib/adodb/adodb.inc.php');

    include $local_php;
    $dbTiki = ADONewConnection($db_tiki);
    $dbTiki->Connect($host_tiki, $user_tiki, $pass_tiki, $dbs_tiki);

    $installer = new Installer;
    $installer->update();
}
else
    `sh doc/devtools/sqlupgrade.sh`;

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
