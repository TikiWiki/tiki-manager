<?php
ob_start();
require dirname(__FILE__) . "/../config.php";
require dirname(__FILE__) . "/../include/layout/web.php";

require TRIMPATH . '/src/env_setup.php';
ob_end_clean();

if (isset($_POST['id'])) {
    if (( $instance = TikiManager\Application\Instance::getInstance((int) $_POST['id']) ) && ( $source = TikiManager\Application\Instance::getInstance((int) $_POST['source']) )) {
        warning("Initiating backup of {$source->name}");
        $archive = web_backup($source);

        warning("Initiating clone of {$source->name} to {$instance->name}");
        $instance->lock();
//        $instance->restore($source->app, $archive, true);
        $instance->unlock();

        info("Deleting archive...");
        $access = $source->getBestAccess('scripting');
        $access->shellExec("rm -f " . $archive);

        // $archive = $source->backup();
    } else {
        die("Unknown instance.");
    }
}
