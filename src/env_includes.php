<?php
require_once dirname(__FILE__) . '/env_defines.php';
require_once TRIM_ROOT . '/src/Libs/Helpers/functions.php';

run_composer_install();
require_once TRIM_ROOT . '/vendor/autoload.php';

require_once TRIM_ROOT . '/src/dbsetup.php';
