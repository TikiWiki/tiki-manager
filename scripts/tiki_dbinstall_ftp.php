<?php

$local_php = 'db/local.php';

require_once('installer/installlib.php');
include_once ('lib/adodb/adodb.inc.php');

include $local_php;
$dbTiki = ADONewConnection($db_tiki);
$dbTiki->Connect($host_tiki, $user_tiki, $pass_tiki, $dbs_tiki);

$installer = new Installer;
$installer->cleanInstall();
