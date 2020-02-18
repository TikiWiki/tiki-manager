<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence   Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

use TikiManager\Application\Tiki;

ini_set('zlib.output_compression', 0);
header('Content-Encoding: none'); //Disable apache compression

ob_start();
require dirname(__FILE__) . "/../config.php";
require TRIMPATH . '/vendor/autoload.php';
$environment = new TikiManager\Config\Environment(TRIMPATH);
$environment->load();
ob_end_clean();

ob_implicit_flush(true);
ob_end_flush();

if (! empty($_POST['source'])
    && ! empty($_POST['branch'])
) {
    if ($instance = TikiManager\Application\Instance::getInstance((int) $_POST['source'])) {
        $tikiApplication = new Tiki($instance);
        $versions_raw = $tikiApplication->getVersions();

        foreach ($versions_raw as $version) {
            if ($version->branch == $_POST['branch']) {
                $versionSel = $version;
                break;
            }
        }

        if (empty($versionSel)) {
            warning("Unknown branch.");
            die();
        }

        try {
            $locked = $instance->lock();
            info('Instance locked');
            $app = $instance->getApplication();
            $filesToResolve = $app->performUpdate($instance, $versionSel);

            if ($locked) {
                $instance->unlock();
                info('Instance unlocked');
            }
        } catch (\Exception $e) {
            error($e->getMessage());
            exit(-1);
        }
    } else {
        warning("Unknown instance");
    }
} else {
    warning("ERROR!");
}
