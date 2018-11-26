<?php
ob_start();
require dirname(__FILE__) . "/../config.php";
require dirname(__FILE__) . "/../include/layout/web.php";

require TRIMPATH . '/src/env_setup.php';
require TRIMPATH . '/src/clean.php';
ob_end_clean();

if (isset($_POST['id'])) {
    if ($instance = Instance::getInstance((int) $_POST['id'])) {
        web_backup($instance);
//        $instance->backup();
//        perform_archive_cleanup($instance->id, $instance->name);
    } else {
        die("Unknown instance.");
    }
}
