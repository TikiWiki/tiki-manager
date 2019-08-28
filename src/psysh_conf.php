<?php
require __DIR__ . '/../vendor/autoload.php';
$environment = new TikiManager\Config\Environment(__DIR__ . '/../');
$environment->load();
$GLOBALS['db'] = $db;
