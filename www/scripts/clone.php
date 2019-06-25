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

require TRIMPATH . '/src/env_setup.php';
ob_end_clean();

ob_implicit_flush(true);
ob_end_flush();

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
