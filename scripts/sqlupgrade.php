<?php

if( $root = $_SERVER['argv'][1] )
{
	chdir( $root );
}

if( file_exists( 'installer/shell.php' ) )
{
	require_once('lib/init/initlib.php');
	require_once('lib/setup/tikisetup.class.php');
	TikiSetup::prependIncludePath($root);
	TikiSetup::prependIncludePath('lib');
	TikiSetup::prependIncludePath('lib/pear');
	require_once('tiki-setup_base.php');
	require_once('installer/installlib.php');
	include $local_php;

	$installer = new Installer;
	$installer->update();
}
else
{
	`sh doc/devtools/sqlupgrade.sh`;
}

?>
