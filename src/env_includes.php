<?php
require_once __DIR__ . '/env_defines.php';
require_once __DIR__ . '/Libs/Helpers/functions.php';
if (! IS_PHAR) {
    run_composer_install();
}
require_once __DIR__ . '/../vendor/autoload.php';
