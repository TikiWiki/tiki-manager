<?php
define('TRIM_ROOT', realpath(dirname(__DIR__)));
define('TRIM_DEBUG', getenv('TRIM_DEBUG') === 'true');
define('TRIM_OUTPUT', TRIM_ROOT . "/logs/trim.output");

define('CACHE_FOLDER', TRIM_ROOT . "/cache");
define('TEMP_FOLDER', TRIM_ROOT . "/tmp");
define('RSYNC_FOLDER', TRIM_ROOT . "/tmp/rsync");
define('MOUNT_FOLDER', TRIM_ROOT . "/tmp/mount");
define('BACKUP_FOLDER', TRIM_ROOT . "/backup");
define('ARCHIVE_FOLDER', TRIM_ROOT . "/backup/archive");

define('TRIM_DATA', TRIM_ROOT . "/data");
define('DB_FILE', TRIM_DATA . "/trim.db");
define('SSH_CONFIG', TRIM_DATA . "/ssh_config");

define('TRIM_OS', strtoupper(substr(PHP_OS, 0, 3)));

if (TRIM_OS === 'WIN') {
    define('INTERACTIVE', php_sapi_name() === 'cli'
        && getenv('NONINTERACTIVE') !== 'true');
} else {
    define('INTERACTIVE',
        php_sapi_name() === 'cli'
        && getenv('NONINTERACTIVE') !== 'true'
        && !in_array(getenv('TERM'), array('dumb', false, ''))
        && preg_match(',^/dev/,', exec('tty'))
    );
}

if (file_exists(getenv('HOME') . '/.ssh/id_rsa') &&
    file_exists(getenv('HOME') . '/.ssh/id_rsa.pub')) {

    define('SSH_KEY', getenv('HOME') . '/.ssh/id_rsa');
    define('SSH_PUBLIC_KEY', getenv('HOME') . '/.ssh/id_rsa.pub');
}

if (!defined('SSH_KEY') && !defined('SSH_PUBLIC_KEY')) {
    define('SSH_KEY', TRIM_ROOT . "/data/id_rsa");
    define('SSH_PUBLIC_KEY', TRIM_ROOT . "/data/id_rsa.pub");
}

if (array_key_exists('EDITOR', $_ENV))
    define('EDITOR', $_ENV['EDITOR']);
else {
    define('EDITOR', 'nano');
}

if (array_key_exists('DIFF', $_ENV))
    define('DIFF', $_ENV['DIFF']);
else {
    define('DIFF', 'diff');
}
