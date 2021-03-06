<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence   Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

use TikiManager\Application\Tiki;
use TikiManager\Config\App;
use TikiManager\Config\Environment;

ini_set('zlib.output_compression', 0);
header('Content-Encoding: none'); //Disable apache compression

ob_start();
require dirname(__FILE__) . "/../config.php";
require TRIMPATH . '/vendor/autoload.php';
Environment::getInstance()->load();
$io = App::get('io');

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
            $io->warning("Unknown branch.");
            die();
        }

        try {
            $locked = $instance->lock();
            $io->writeln('Instance locked');
            $app = $instance->getApplication();
            $filesToResolve = $app->performUpdate($instance, $versionSel);

            if ($locked) {
                $instance->unlock();
                $io->writeln('Instance unlocked');
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            exit(-1);
        }
    } else {
        $io->warning("Unknown instance");
    }
} else {
    $io->warning("ERROR!");
}
