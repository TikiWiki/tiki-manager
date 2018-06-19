<?php
require_once dirname(__FILE__) . '/env_defines.php';
require_once TRIM_ROOT . '/src/libs/helpers/functions.php';

run_composer_install();
require_once TRIM_ROOT . '/vendor/autoload.php';

require_once TRIM_ROOT . '/src/libs/helpers/Wrapper.php';
require_once TRIM_ROOT . '/src/libs/host/HostCommand.php';
require_once TRIM_ROOT . '/src/libs/host/LocalHost.php';
require_once TRIM_ROOT . '/src/libs/host/FTPHost.php';
require_once TRIM_ROOT . '/src/libs/host/SSHHost.php';
require_once TRIM_ROOT . '/src/libs/audit/Checksum.php';
require_once TRIM_ROOT . '/src/libs/trim/Discovery.php';
require_once TRIM_ROOT . '/src/libs/trim/Backup.php';
require_once TRIM_ROOT . '/src/libs/trim/Restore.php';
require_once TRIM_ROOT . '/src/libs/trim/Instance.php';
require_once TRIM_ROOT . '/src/libs/trim/Version.php';
require_once TRIM_ROOT . '/src/accesslib.php';
require_once TRIM_ROOT . '/src/applicationlib.php';
require_once TRIM_ROOT . '/src/libs/database/Database.php';
require_once TRIM_ROOT . '/src/rclib.php';
require_once TRIM_ROOT . '/src/channellib.php';
require_once TRIM_ROOT . '/src/backupreportlib.php';
require_once TRIM_ROOT . '/src/reportlib.php';
require_once TRIM_ROOT . '/src/ext/Password.php';
