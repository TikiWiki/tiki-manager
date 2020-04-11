<?php

require __DIR__ . '/../vendor/autoload.php';

\TikiManager\Config\Environment::getInstance()->load();
$GLOBALS['db'] = $db;
