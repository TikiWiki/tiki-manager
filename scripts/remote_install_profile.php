<?php
if( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
	$_SERVER['argv'] = $_GET;
}

chdir( $_SERVER['argv'][1] );
require_once 'tiki-setup.php';
require_once 'lib/profilelib/profilelib.php';
require_once 'lib/profilelib/installlib.php';

$profile = Tiki_Profile::fromNames( $_SERVER['argv'][2], $_SERVER['argv'][3] );

$installer = new Tiki_Profile_Installer;
$installer->install( $profile );
