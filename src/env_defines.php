<?php
$pharPath = Phar::running(false);
define('TRIM_ROOT', realpath(dirname((isset($pharPath) && !empty($pharPath)) ? $pharPath : __DIR__)));
define('IS_PHAR', (isset($pharPath) && !empty($pharPath)));
define('EXECUTABLE_SCRIPT', [
    'scripts/checkversion.php',
    'scripts/package_tar.php',
    'scripts/extract_tar.php',
    'scripts/get_extensions.php',
    'scripts/tiki/backup_database.php',
    'scripts/tiki/get_directory_list.php',
    'scripts/tiki/remote_install_profile.php',
    'scripts/tiki/sqlupgrade.php',
    'scripts/tiki/run_sql_file.php',
    'scripts/tiki/tiki_dbinstall_ftp.php',
    'scripts/tiki/remote_setup_channels.php',
    'scripts/tiki/mysqldump.php',
    'scripts/maintenance.htaccess'
]);
define('ROOT_PATH', __DIR__);
define('TRIM_DEBUG', getenv('TRIM_DEBUG') === 'true');
define('TRIM_LOGS', TRIM_ROOT . "/logs");
define('TRIM_OUTPUT', TRIM_LOGS . "/trim.output");

define('CACHE_FOLDER', TRIM_ROOT . "/cache");
define('TEMP_FOLDER', TRIM_ROOT . "/tmp");
define('RSYNC_FOLDER', TRIM_ROOT . "/tmp/rsync");
define('MOUNT_FOLDER', TRIM_ROOT . "/tmp/mount");
define('SCRIPTS_FOLDER', TRIM_ROOT . "/scripts");
define('BACKUP_FOLDER', TRIM_ROOT . "/backup");
define('ARCHIVE_FOLDER', TRIM_ROOT . "/backup/archive");

define('TRIM_DATA', TRIM_ROOT . "/data");
define('DB_FILE', TRIM_DATA . "/trim.db");
define('SSH_CONFIG', TRIM_DATA . "/ssh_config");

define('TRIM_OS', strtoupper(substr(PHP_OS, 0, 3)));

define('PDO_ATTR_TIMEOUT', 10);
define('PDO_EXTENDED_DEBUG', false);
define('PDO_DIE_ON_EXCEPTION_THROWN', true);

define('CONFIGURATION_FILE_PATH', TRIM_ROOT . '/data/config.yml');
define('DEFAULT_VERSION_CONTROL_SYSTEM', 'SVN');

define('TIKI_MANAGER_EXECUTABLE', 'tiki-manager');

define('SVN_TIKIWIKI_URI', getenv('SVN_TIKIWIKI_URI') ?: 'https://svn.code.sf.net/p/tikiwiki/code');
define('GIT_TIKIWIKI_URI', getenv('GIT_TIKIWIKI_URI') ?: 'https://gitlab.com/tikiwiki/tiki.git');

if (TRIM_OS === 'WIN') {
    define('INTERACTIVE', php_sapi_name() === 'cli'
        && getenv('NONINTERACTIVE') !== 'true');
} else {
    define(
        'INTERACTIVE',
        php_sapi_name() === 'cli'
        && getenv('NONINTERACTIVE') !== 'true'
        && !in_array(getenv('TERM'), ['dumb', false, ''])
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

if (array_key_exists('EDITOR', $_ENV)) {
    define('EDITOR', $_ENV['EDITOR']);
} else {
    define('EDITOR', 'nano');
}

if (array_key_exists('DIFF', $_ENV)) {
    define('DIFF', $_ENV['DIFF']);
} else {
    define('DIFF', 'diff');
}

if (file_exists(__DIR__ . "/../composer.phar")) {
    define("COMPOSER_PATH", "php " . TRIM_ROOT . "/composer.phar");
} else {
    define("COMPOSER_PATH", "composer");
}
