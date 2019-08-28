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

$authFile = dirname(__FILE__) . "/../config.php";

ob_start();
require $authFile;
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
    if ($instance = TikiManager\Application\Instance::getInstance((int) $_POST['id'])) {
        $log = '';
        $email = $instance->contact;
        $version = $instance->getLatestVersion();

        if ($version->hasChecksums()) {
            $result = $version->performCheck($instance);
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
        }
        if (empty($log)) {
            info("Nothing found.");
        } else {
            warning("Potential intrusions detected.");
            error($log);
        }
    } else {
        die("Unknown instance.");
    }
}
