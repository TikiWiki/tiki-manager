<?php
ob_start();
require dirname(__FILE__) . "/../config.php";
require TRIMPATH . '/src/env_setup.php';
ob_end_clean();

if (isset($_POST['id'])) {
    if ($instance = Instance::getInstance((int) $_POST['id'])) {
        $locked = (md5_file(TRIMPATH . '/scripts/maintenance.htaccess') == md5_file($instance->getWebPath('.htaccess')));
        if (! $locked) {
            $locked = $instance->lock();
        }
        $instance->detectPHP();
        $app = $instance->getApplication();
        $app->performUpdate($instance);
        $version = $instance->getLatestVersion();
        if ($locked) {
            $instance->unlock();
        }
    } else {
        die("Unknown instance.");
    }
}
