<?php
ob_start();
require dirname(__FILE__) . "/../config.php";
require TRIMPATH . '/src/env_setup.php';
ob_end_clean();

if (isset($_POST['id'])){
    if (( $instance = Instance::getInstance( (int) $_POST['id'] ) ) && ( $source = Instance::getInstance( (int) $_POST['source'] ) )) {

//        $archive = $_POST['backup'];
//        $base = basename($archive);
//        list($basetardir, $trash) = explode('_', $base, 2);
//        $remote = $instance->getWorkPath($base);

//        $access = $instance->getBestAccess('scripting');
//        $access->uploadFile($archive, $remote);

        $instance->restore($source->app, $_POST['backup']);
        echo "\nIt is now time to test your site: " . $instance->name . "\n";
        echo "\nIf there are issues, connect with make access to troubleshoot directly on the server.\n";
        echo "\nYou'll need to login to this restored instance and update the file paths with the new values.\n";
	} else {
		die( "Unknown instance." );
    }
}
?>
