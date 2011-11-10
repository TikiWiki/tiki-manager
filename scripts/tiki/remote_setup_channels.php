<?php
if( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
	$_SERVER['argv'] = $_GET;
}

chdir( $_SERVER['argv'][1] );
require_once 'tiki-setup.php';

$password = md5($_SERVER['argv'][1] . time());

$userlib->add_user('trim_user', $password, $_SERVER['argv'][2] );
$userlib->assign_user_to_group( 'trim_user', 'TRIM' );

$channels = trim( $tikilib->get_preference( 'profile_channels' ) ) . <<<NEW

trim_backup_summary, tiki://local, TRIM_Backup_Summary_Channel, TRIM
trim_backup_detail, tiki://local, TRIM_Backup_Detail_Channel, TRIM
NEW;

$tikilib->set_preference( 'profile_channels', $channels );

echo $password;
