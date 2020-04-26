<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @copyright (c) 2016, Avan.Tech, et. al.
 * @copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

use TikiManager\Config\Environment;

ini_set('zlib.output_compression', 0);
header('Content-Encoding: none'); //Disable apache compression

ob_start();
require dirname(__FILE__) . "/../config.php";
require TRIMPATH . '/vendor/autoload.php';
Environment::getInstance()->load();
$io = App::get('io');

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
            $io->error($e->getMessage());
            exit(-1);
        }

        $io->newLine();
        $io->writeln("It is now time to test your site: " . $instance->name);
        $io->newLine();
        $io->writeln("If there are issues, connect with 'tiki-manager instance:access' to troubleshoot directly on the server.");
        $io->newLine();
        $io->writeln("You'll need to login to this restored instance and update the file paths with the new values.");
    } else {
        $io->error("Unknown instance.");
        die();
    }
}
