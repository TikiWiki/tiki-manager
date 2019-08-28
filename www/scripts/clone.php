<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @copyright (c) 2016, Avan.Tech, et. al.
 * @copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */
use TikiManager\Application\Instance;
use TikiManager\Libs\Database\Database;
use TikiManager\Command\Helper\CommandHelper;

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

$sourceInstanceId = (int)$_POST['source'] ?? 0;
$targetInstanceId = (int)$_POST['id'] ?? 0;
$sourceInstance = TikiManager\Application\Instance::getInstance($sourceInstanceId);
$targetInstance = TikiManager\Application\Instance::getInstance($targetInstanceId);

if (!empty($sourceInstanceId) && !empty($targetInstance)) {
    try {
        warning("Initiating backup of {$sourceInstance->name}");
        $archive = $sourceInstance->backup();

        warning("Initiating clone of {$sourceInstance->name} to {$targetInstance->name}");
        $targetInstance->lock();
        $targetInstance->restore($sourceInstance->app, $archive, true);
        $targetInstance->unlock();

        info("Deleting archive...");
        $access = $sourceInstance->getBestAccess('scripting');
        $access->shellExec("rm -f " . $archive);
    } catch (Exception $ex) {
        error($ex->getMessage());
    }
} else {
    error("Unknown instance.");
}
