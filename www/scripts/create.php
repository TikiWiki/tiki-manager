<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

use Symfony\Component\Process\Process;
use TikiManager\Access\Local;
use TikiManager\Application\Instance;

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

if (! empty($_POST['type'])
    && ! empty($_POST['name'])
    && ! empty($_POST['contact'])
    && ! empty($_POST['webroot'])
    && ! empty($_POST['weburl'])
    && ! empty($_POST['tempdir'])
    && ! empty($_POST['backup_user'])
    && ! empty($_POST['backup_group'])
    && ! empty($_POST['backup_perm'])
    && ! empty($_POST['branch'])
    && ! empty($_POST['dbHost'])
    && ! empty($_POST['dbUser'])
    && ! empty($_POST['dbPass'])
    && ! empty($_POST['dbPrefix'])
) {
    // To detect Tiki Manager instance PHP cli
    $instance = new Instance();
    $instance->type = 'local';
    $access = new Local($instance);
    $phpPath = $access->getInterpreterPath();

    $command = [
        $phpPath,
        TRIMPATH . '/tiki-manager.php',
        'instance:create',
        '--type=' . $_POST['type'],
        '--url=' . $_POST['weburl'],
        '--name=' . $_POST['name'],
        '--email=' . $_POST['contact'],
        '--webroot=' . $_POST['webroot'],
        '--tempdir=' . $_POST['tempdir'],
        '--branch=' . $_POST['branch'],
        '--backup-user=' . $_POST['backup_user'],
        '--backup-group=' . $_POST['backup_group'],
        '--backup-permission=' . $_POST['backup_perm'],
        '--db-host=' . $_POST['dbHost'],
        '--db-user=' . $_POST['dbUser'],
        '--db-pass=' . $_POST['dbPass'],
        '--db-prefix=' . $_POST['dbPrefix'],
    ];

    // Unset REQUEST_METHOD to be able to trigger tiki's console.php
    $process = new Process($command, null, ['REQUEST_METHOD' => false]);
    $process->setTimeout(600);
    $process->run(function ($type, $buffer) {
        echo $buffer . appendFlush();
    });
} else {
    warning("ERROR!");
}
