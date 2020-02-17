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
require TRIMPATH . '/vendor/autoload.php';
$environment = new TikiManager\Config\Environment(TRIMPATH);
$environment->load();
ob_end_clean();

if (defined('TIMEOUT')) {
    set_time_limit(TIMEOUT);
}

ob_implicit_flush(true);
ob_end_flush();

if (isset($_POST['id'])) {
    if (( $instance = TikiManager\Application\Instance::getInstance((int) $_POST['id']) ) && ( $source = TikiManager\Application\Instance::getInstance((int) $_POST['source']) )) {
//        $archive = $_POST['backup'];
//        $base = basename($archive);
//        list($basetardir, $trash) = explode('_', $base, 2);
//        $remote = $instance->getWorkPath($base);

//        $access = $instance->getBestAccess('scripting');
//        $access->uploadFile($archive, $remote);
        try {
            $instance->restore($source->app, $_POST['backup']);
        } catch (\Exception $e) {
            error($e->getMessage());
            exit(-1);
        }

        echo "\nIt is now time to test your site: " . $instance->name . "\n";
        echo "\nIf there are issues, connect with 'tiki-manager instance:access' to troubleshoot directly on the server.\n";
        echo "\nYou'll need to login to this restored instance and update the file paths with the new values.\n";
    } else {
        die("Unknown instance.");
    }
}
