<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'GET')
	$_SERVER['argv'] = $_GET;

chdir($_SERVER['argv'][1]);
require_once 'tiki-setup.php';

$password = md5($_SERVER['argv'][1] . time());

$userlib->add_user('trim_user', $password, $_SERVER['argv'][2]);
$userlib->assign_user_to_group('trim_user', 'TRIM');

$channels = trim($tikilib->get_preference('profile_channels')) . <<<NEW

trim_backup_summary, tiki://local, TRIM_Backup_Summary_Channel, TRIM
trim_backup_detail, tiki://local, TRIM_Backup_Detail_Channel, TRIM
NEW;

$tikilib->set_preference('profile_channels', $channels);

echo $password;

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
