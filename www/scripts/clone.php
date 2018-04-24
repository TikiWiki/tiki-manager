<?php
$authFile = dirname(__FILE__) . "/../config.php";

ob_start();
require $authFile;
require TRIMPATH . '/src/env_setup.php';
require TRIMPATH . '/src/dbsetup.php';
ob_end_clean();

if (isset($_POST['id'])){
    if (( $instance = Instance::getInstance( (int) $_POST['id'] ) ) && ( $source = Instance::getInstance( (int) $_POST['source'] ) )) {
        $archive = $source->backup();
        if ($archive === null) {
            echo color("\nError: Snapshot creation failed.\n", 'red');
            exit(1);
        }

        $app = $source->getApplication();
        info("Initiating clone of {$source[0]->name} to {$instance->name}");
        $instance->restore($source->app, $archive, true);
	} else {
		die( "Unknown instance." );
    }
}
?>
