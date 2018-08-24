<?php
$authFile = dirname(__FILE__) . "/../config.php";

ob_start();
require $authFile;
require TRIMPATH . '/src/env_setup.php';
require TRIMPATH . '/src/clean.php';
ob_end_clean();

if (isset($_POST['id'])){
    if ( $instance = Instance::getInstance( (int) $_POST['id'] ) ) {
        info('Checking permissions before backup...');
        $instance->getApplication()->fixPermissions();
        info('Check done!');
        info('Backing up instance...');
        $instance->backup();
//        perform_archive_cleanup($instance->id, $instance->name);
    } else {
        die( "Unknown instance." );
    }
}
?>
