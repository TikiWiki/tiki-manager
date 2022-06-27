<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET') {
    $_SERVER['argv'] = $_GET;
}

chdir($_SERVER['argv'][1]);
require_once 'tiki-setup.php';
require_once 'lib/core/Tiki/Profile/Installer.php';
//require_once 'lib/profilelib/profilelib.php';
//require_once 'lib/profilelib/installlib.php';

if ($profile) {
    $installer = new Tiki_Profile_Installer;
    $installer->install($profile);
    die('<info>Profile applied.</info>');
} else {
    die('<error>Profile not found.</error>');
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
