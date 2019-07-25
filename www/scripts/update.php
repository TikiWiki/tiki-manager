<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @copyright (c) 2016, Avan.Tech, et. al.
 * @copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

ini_set('zlib.output_compression', 0);
header('Content-Encoding: none'); //Disable apache compression

ob_start();
require dirname(__FILE__) . "/../config.php";
require TRIMPATH . '/src/env_setup.php';
ob_end_clean();

if (defined('TIMEOUT')) {
    set_time_limit(TIMEOUT);
}

ob_implicit_flush(true);
ob_end_flush();

if (isset($_POST['id'])) {
    if ($instance = TikiManager\Application\Instance::getInstance((int) $_POST['id'])) {
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
