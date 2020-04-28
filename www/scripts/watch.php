<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @copyright (c) 2016, Avan.Tech, et. al.
 * @copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

use TikiManager\Config\App;
use TikiManager\Config\Environment;

ini_set('zlib.output_compression', 0);
header('Content-Encoding: none'); //Disable apache compression

$authFile = dirname(__FILE__) . "/../config.php";

ob_start();
require $authFile;
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
    if ($instance = TikiManager\Application\Instance::getInstance((int) $_POST['id'])) {
        $log = '';
        $email = $instance->contact;
        $version = $instance->getLatestVersion();

        if ($version->hasChecksums()) {
            try {
                $result = $version->performCheck($instance);
            } catch (\Exception $e) {
                $io->error($e->getMessage());
                exit(-1);
            }
        }

        if (count($result['new']) || count($result['mod']) || count($result['del'])) {
            $log .= "{$instance->name} ({$instance->weburl})\n";

            foreach ($result['new'] as $file => $hash) {
                $log .= "+ $file\n";
            }
            foreach ($result['mod'] as $file => $hash) {
                $log .= "o $file\n";
            }
            foreach ($result['del'] as $file => $hash) {
                $log .= "- $file\n";
            }

            $log .= "\n\n";
        }

        if (empty($log)) {
            $io->writeln("Nothing found.");
        } else {
            $io->warning("Potential intrusions detected.");
            $io->text($log);
        }
    } else {
        $io->error('Unknown instance');
        exit(1);
    }
}
