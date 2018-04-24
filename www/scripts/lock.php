<?php
$authFile = dirname(__FILE__) . "/../config.php";

ob_start();
require $authFile;
require TRIMPATH . '/src/env_setup.php';
ob_end_clean();

if (isset($_POST['id'])){
	if ( $instance = Instance::getInstance( (int) $_POST['id'] ) ) {
        $instance->lock();
    } else {
        die( "Unknown instance." );
    }
}
?>
