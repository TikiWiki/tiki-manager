<?php

$authFile = dirname(__FILE__) . "/config.php";

if (! file_exists($authFile)) {
    die("This interface is not enabled.");
}

ob_start();
require $authFile;
require TRIMPATH . '/src/env_setup.php';
ob_end_clean();

if (RESTRICT && ( $_SERVER['HTTP_HOST'] != 'localhost' || $_SERVER['REMOTE_ADDR'] != '127.0.0.1' )) {
    die("This interface is not enabled.");
}

set_time_limit(TIMEOUT);

session_start();

if (! isset($_SESSION['active'])) {
    require "include/login.php";
    exit;
}

$op = isset($_GET['op']) ? $_GET['op'] : '' ;
$id = isset($_GET['op']) ? (int) $_GET['id'] : 0;

$loc = strrpos($_SERVER['REQUEST_URI'], $op);
if (! $loc) {
    $loc = strlen($_SERVER['REQUEST_URI']);
}
define('PRIOR', substr($_SERVER['REQUEST_URI'], 0, $loc));

function html($string)
{
    return htmlentities($string, ENT_COMPAT, 'UTF-8');
}

function url($relative)
{
    if (PRIOR == 'PRIOR') {
        return $relative;
    } else {
        return PRIOR . $relative;
    }
}

if (empty($op)) {
    $op = 'list';
}

if (! in_array($op, [ 'backup', 'blank', 'clone', 'cloneupgrade', 'create', 'delete', 'edit', 'fix', 'import', 'list', 'manage', 'restore', 'update', 'upgrade', 'view', 'watch', 'logout' ])) {
    die("Unknown operation.");
}

if (in_array($op, [ 'view', 'edit' ]) && $id == 0) {
    die("ID required.");
} else {
    $instance = TikiManager\Application\Instance::getInstance($id);
}

/**
 * Get current location base URL
 *
 * @return string
 */
function getBaseUrl()
{
    $baseUrl = '';

    if (isset($_SERVER['HTTP_HOST'])) {
        $http = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $hostname = $_SERVER['HTTP_HOST'];
        $dir =  str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
        $baseUrl = sprintf('%s://%s%s', $http, $hostname, $dir);
    }

    return $baseUrl;
}

require "include/$op.php";
