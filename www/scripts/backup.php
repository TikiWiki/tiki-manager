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
require dirname(__FILE__) . "/../include/layout/web.php";

if (defined('TIMEOUT')) {
    set_time_limit(TIMEOUT);
}

require TRIMPATH . '/vendor/autoload.php';
$environment = new TikiManager\Config\Environment(TRIMPATH);
$environment->load();
ob_end_clean();

ob_implicit_flush(true);
ob_end_flush();

if (isset($_POST['id'])) {
    if ($instance = TikiManager\Application\Instance::getInstance((int) $_POST['id'])) {
        try {
            web_backup($instance);
        } catch (\Exception $e) {
            error($e->getMessage());
            exit(-1);
        }
//        $instance->backup();
//        TikiManager\Helpers\Archive::performArchiveCleanup($instance->id, $instance->name);
    } else {
        die("Unknown instance.");
    }
}
