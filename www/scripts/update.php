<?php
$authFile = dirname(__FILE__) . "/../config.php";

ob_start();
require $authFile;
require TRIMPATH . '/src/env_setup.php';
require TRIMPATH . '/src/dbsetup.php';
ob_end_clean();

if (isset($_POST['id'])){
	if ( $instance = Instance::getInstance( (int) $_POST['id'] ) ) {
		$locked = (md5_file(TRIMPATH . '/scripts/maintenance.htaccess') == md5_file($instance->getWebPath('.htaccess')));
		if (! $locked) $locked = $instance->lock();

		$instance->detectPHP();
		$app = $instance->getApplication();
//		perform_instance_installation($instance);
		$app->performUpdate($instance);
		if ($locked) $instance->unlock();
    } else {
        die( "Unknown instance." );
    }
}
?>
