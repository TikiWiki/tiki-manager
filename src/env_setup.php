<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.


define('TRIM_DEBUG', getenv('TRIM_DEBUG') === 'true');
define('TRIM_ROOT', realpath(dirname(__DIR__)));

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
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

require_once TRIM_ROOT . '/vendor/autoload.php';
require_once TRIM_ROOT . '/src/libs/helpers.php';
require_once TRIM_ROOT . '/src/libs/host/LocalHost.php';
require_once TRIM_ROOT . '/src/libs/host/FTPHost.php';
require_once TRIM_ROOT . '/src/libs/host/SSHHost.php';
require_once TRIM_ROOT . '/src/libs/audit/Checksum.php';
require_once TRIM_ROOT . '/src/libs/trim/Instance.php';
require_once TRIM_ROOT . '/src/libs/trim/Version.php';
require_once TRIM_ROOT . '/src/accesslib.php';
require_once TRIM_ROOT . '/src/applicationlib.php';
require_once TRIM_ROOT . '/src/databaselib.php';
require_once TRIM_ROOT . '/src/rclib.php';
require_once TRIM_ROOT . '/src/channellib.php';
require_once TRIM_ROOT . '/src/backupreportlib.php';
require_once TRIM_ROOT . '/src/reportlib.php';
require_once TRIM_ROOT . '/src/ext/Password.php';

debug('Running TRIM at ' . TRIM_ROOT);
define('TRIM_DATA', TRIM_ROOT . "/data");
define('DB_FILE', TRIM_DATA . "/trim.db");
define('SSH_CONFIG', TRIM_DATA . "/ssh_config");

define('CACHE_FOLDER', TRIM_ROOT . "/cache");
define('TEMP_FOLDER', TRIM_ROOT . "/tmp");
define('RSYNC_FOLDER', TRIM_ROOT . "/tmp/rsync");
define('MOUNT_FOLDER', TRIM_ROOT . "/tmp/mount");
define('BACKUP_FOLDER', TRIM_ROOT . "/backup");
define('ARCHIVE_FOLDER', TRIM_ROOT . "/backup/archive");
define('TRIM_OUTPUT', TRIM_ROOT . "/logs/trim.output");

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    define('TRIM_TEMP', getenv('TEMP')."\\trim_temp");
} else {
    define('TRIM_TEMP', '/tmp/trim_temp');
    secure_trim_data(true);
}


if (file_exists(getenv('HOME') . '/.ssh/id_rsa') &&
    file_exists(getenv('HOME') . '/.ssh/id_rsa.pub')) {

    define('SSH_KEY', getenv('HOME') . '/.ssh/id_rsa');
    define('SSH_PUBLIC_KEY', getenv('HOME') . '/.ssh/id_rsa.pub');
}

if (file_exists(getenv('HOME') . '/.ssh/id_dsa') &&
    file_exists(getenv('HOME') . '/.ssh/id_dsa.pub') &&
    !defined('SSH_KEY') &&
    !defined('SSH_PUBLIC_KEY')) {

    warning(
        sprintf('Ssh-dsa key (%s and %s) was found but TRIM won\'t used it, ' .
            'because DSA was deprecated in openssh-7.0. ' .
            'If you need a new RSA key, run \'make copysshkey\' and TRIM will create a new one.' .
            'Copy the new key to all your instances.',
            SSH_KEY, SSH_PUBLIC_KEY
        )
    );
}

if (file_exists(TRIM_ROOT . "/data/id_dsa") &&
    file_exists(TRIM_ROOT . "/data/id_dsa.pub") &&
    !defined('SSH_KEY') &&
    !defined('SSH_PUBLIC_KEY')) {

    warning(
        sprintf('Ssh-dsa key (%s and %s) was found but TRIM won\'t used it, ' .
            'because DSA was deprecated in openssh-7.0. ' .
            'If you need a new RSA key, run \'make copysshkey\' and TRIM will create a new one.' .
            'Copy the new key to all your instances.',
            SSH_KEY, SSH_PUBLIC_KEY
        )
    );
}

if (!defined('SSH_KEY') && !defined('SSH_PUBLIC_KEY')) {
    define('SSH_KEY', TRIM_ROOT . "/data/id_rsa");
    define('SSH_PUBLIC_KEY', TRIM_ROOT . "/data/id_rsa.pub");
}

if (array_key_exists('EDITOR', $_ENV))
    define('EDITOR', $_ENV['EDITOR']);
else {
    trim_debug('Default editor used (nano). ' .
        'You can change the EDITOR environment variable.');
    define('EDITOR', 'nano');
}

if (array_key_exists('DIFF', $_ENV))
    define('DIFF', $_ENV['DIFF']);
else {
    trim_debug('Default diff used (diff). ' .
        'You can change the DIFF environment variable.');
    define('DIFF', 'diff');
}

// Check for required extensions
if (! in_array('sqlite', PDO::getAvailableDrivers()))
    die(error("The SQLite PHP extension is not available. Install to continue."));

// Check for required system dependencies
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $ssh = 'where ssh';
    $kg = 'where ssh-keygen';
} else {
    $ssh = `export PATH; which ssh`;
    $kg = `export PATH; which ssh-keygen`;
}

if (empty($ssh) || empty($kg)) {
    die(error("SSH tools not installed on current machine. " .
        "Make sure 'ssh' and 'ssh-keygen' and are installed.\n"));
}

// Make sure SSH is set-up
if (! file_exists(SSH_KEY) || ! file_exists(SSH_PUBLIC_KEY)) {
    if (! is_writable(dirname(SSH_KEY)))
        die(error('Impossible to generate SSH key. Make sure data folder is writable.'));

    echo 'If you enter a passphrase, you will need to enter it every time you run ' .
        'TRIM, and thus, automatic, unattended operations (like backups, file integrity ' .
        "checks, etc.) will not be possible.\n";

    $key = SSH_KEY;
    `ssh-keygen -t rsa -f $key`;
}

if (! file_exists(CACHE_FOLDER))
    mkdir(CACHE_FOLDER);
if (! file_exists(TEMP_FOLDER))
    mkdir(TEMP_FOLDER);
if (! file_exists(RSYNC_FOLDER))
    mkdir( RSYNC_FOLDER );
if (! file_exists(MOUNT_FOLDER))
    mkdir(MOUNT_FOLDER);
if (! file_exists(BACKUP_FOLDER))
    mkdir(BACKUP_FOLDER);
if (! file_exists(ARCHIVE_FOLDER))
    mkdir(ARCHIVE_FOLDER);

function trim_output($output)
{
    $fh = fopen(TRIM_OUTPUT, 'a+');
    if (is_resource($fh)) {
        fprintf($fh, "%s\n", $output);
        fclose($fh);
    }
}

function trim_debug($output)
{
    if (TRIM_DEBUG) trim_output($output);
}

function cache_folder($app, $version)
{
    $key = sprintf('%s-%s-%s', $app->getName(), $version->type, $version->branch);
    $key = str_replace('/', '_', $key);
    $folder = CACHE_FOLDER . "/$key";

    return $folder;
}

// Make sure the raw database exists
if (! file_exists(DB_FILE)) {
    if (! is_writable(dirname(DB_FILE)))
        die(error('Impossible to generate database. Make sure data folder is writable.'));

    try {
        $db = new PDO('sqlite:' . DB_FILE);
    } catch (PDOException $e) {
        die(error("Could not create the database for an unknown reason. SQLite said: {$e->getMessage()}"));
    }
    
    $db->exec('CREATE TABLE info (name VARCHAR(10), value VARCHAR(10), PRIMARY KEY(name));');
    $db->exec("INSERT INTO info (name, value) VALUES('version', '0');");
    $db = null;

    $file = DB_FILE;
}

try {
    $db = new PDO('sqlite:' . DB_FILE);
} catch (PDOException $e) {
    die(error("Could not connect to the database for an unknown reason. SQLite said: {$e->getMessage()}"));
}

// Obtain the current database version
$result = $db->query("SELECT value FROM info WHERE name = 'version'");
$version = (int)$result->fetchColumn();

// Update the schema to the latest version
// One case per version, no breaks, no failures
switch ($version) {
case 0:
    $db->exec("
        CREATE TABLE instance (
            instance_id INTEGER PRIMARY KEY,
            name VARCHAR(25),
            contact VARCHAR(100),
            webroot VARCHAR(100),
            weburl VARCHAR(100),
            tempdir VARCHAR(100),
            phpexec VARCHAR(50),
            app VARCHAR(10)
        );

        CREATE TABLE version (
            version_id INTEGER PRIMARY KEY,
            instance_id INTEGER,
            type VARCHAR(10),
            branch VARCHAR(50),
            date VARCHAR(25)
        );

        CREATE TABLE file (
            version_id INTEGER,
            path VARCHAR(255),
            hash CHAR(32)
        );

        CREATE TABLE access (
            instance_id INTEGER,
            type VARCHAR(10),
            host VARCHAR(50),
            user VARCHAR(25),
            pass VARCHAR(25)
        );

        UPDATE info SET value = '1' WHERE name = 'version';
    ");
case 1:
    $db->exec("
        CREATE TABLE backup (
            instance_id INTEGER,
            location VARCHAR(200)
        );

        CREATE INDEX version_instance_ix ON version ( instance_id );
        CREATE INDEX file_version_ix ON file ( version_id );
        CREATE INDEX access_instance_ix ON access ( instance_id );
        CREATE INDEX backup_instance_ix ON backup ( instance_id );

        UPDATE info SET value = '2' WHERE name = 'version';
    ");
case 2:
    $db->exec("
        CREATE TABLE report_receiver (
            instance_id INTEGER PRIMARY KEY,
            user VARCHAR(200),
            pass VARCHAR(200)
        );

        CREATE TABLE report_content (
            receiver_id INTEGER,
            instance_id INTEGER
        );

        CREATE INDEX report_receiver_ix ON report_content ( receiver_id );
        CREATE INDEX report_instance_ix ON report_content ( instance_id );

        UPDATE info SET value = '3' WHERE name = 'version';
    ");
case 3:
    $db->exec("
        UPDATE access SET host = (host || ':' || '22') WHERE type = 'ssh';
        UPDATE access SET host = (host || ':' || '22') WHERE type = 'ssh::nokey';
        UPDATE access SET host = (host || ':' || '21') WHERE type = 'ftp';

        UPDATE info SET value = '4' WHERE name = 'version';
    ");
case 4:
    $db->exec("
        CREATE TABLE property (
            instance_id INTEGER NOT NULL,
            key VARCHAR(50) NOT NULL,
            value VARCHAR(200) NOT NULL,
            UNIQUE( instance_id, key )
                ON CONFLICT REPLACE
        );
        UPDATE info SET value = '5' WHERE name = 'version';
    ");
}

// Database access
function query($query, $params = null)
{
    if (is_null($params)) $params = array();
    foreach ($params as $key => $value) {
        if (is_null($value))
            $query = str_replace($key, 'NULL', $query);
        elseif (is_int($value))
            $query = str_replace($key, (int) $value, $query);
        elseif (is_array($value)) {
            error("Unsupported query parameter type: array\n");
            printf("Query\n\"%s\"\nParamters:\n", $query);
            var_dump($params);
            printf("Backtrace:\n");
            debug_print_backtrace();
            exit(1);
        }
        else
            $query = str_replace($key, "'$value'", $query);
    }

    global $db;
    $ret = $db->query($query);

    // TODO: log error
    // if (! $ret) echo "$query\n";

    return $ret;
}

function rowid()
{
    global $db;
    return $db->lastInsertId();
}

// Tools
function findDigits($selection)
{
    // Accept ranges of type 2-10
    $selection = preg_replace_callback('/(\d+)-(\d+)/',
        function ($matches) {
            return implode(' ', range($matches[1], $matches[2]));
        },
        $selection
    );

    preg_match_all('/\d+/', $selection, $matches, PREG_PATTERN_ORDER);
    return $matches[0];
}

function getEntries($list, $selection)
{
    if (! is_array($selection))
        $selection = findDigits($selection);

    $output = array();
    foreach ($selection as $index) {
        if (array_key_exists($index, $list))
            $output[] = $list[$index];
    }

    return $output;
}

/**
 * Ask the user to select one or more instances to perform
 * an action.
 *
 * @param array list of Instance objects
 * @param string $selectionQuestion message displayed to the user before the list of available instances
 * @return array one or more instances objects
 */
function selectInstances(array $instances, $selectionQuestion)
{
    echo $selectionQuestion;

    printInstances($instances);

    $selection = readline('>>> ');
    $selection = getEntries($instances, $selection);

    return $selection;
}

/**
 * Print a list of instances to the user for selection.
 *
 * @param array $instances list of Instance objects
 * @return null
 */
function printInstances(array $instances)
{
    foreach ($instances as $key => $i) {
        $name = substr($i->name, 0, 18);
        $weburl = substr($i->weburl, 0, 38);
        echo "[$i->id] " . str_pad($name, 20) . str_pad($weburl, 40) . str_pad($i->contact, 20) . "\n";
    }
}

/**
 * Prompt for a user value.
 *
 * @param string $prompt Prompt text.
 * @param string $default Default value.
 * @param string $values Acceptable values.
 * @return string User-supplied value.
 */

function promptUser($prompt, $default = false, $values = array())
{
    if(!INTERACTIVE) {
        return $default;
    }

    if (is_array($values) && count($values))
        $prompt .= ' (' . implode(', ', $values) . ')';
    if ($default !== false && strlen($default))
        $prompt .= " [$default]";

    do {
        $answer = trim(readline($prompt . ' : '));
        if (! strlen($answer)) $answer = $default;

        if (is_array($values) && count($values)) {
            if (in_array($answer, $values)) return $answer;
        }
        else if (! is_bool($default)) return $answer;
        else if (strlen($answer)) return $answer;

        error("Invalid response.\n");
    } while (true);
}

function php()
{

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        $paths = `where php`;
    else
        $paths = `whereis php 2>> logs/trim.output`;
    $phps = explode(' ', $paths);

    // Check different versions
    $valid = array();
    foreach ($phps as $interpreter) {
        if (! in_array(basename($interpreter), array('php', 'php5')))
            continue;

        if (! @is_executable($interpreter))
            continue;

        if (@is_dir($interpreter))
            continue;

        $versionInfo = `$interpreter -v`;
        if (preg_match('/PHP (\d+\.\d+\.\d+)/', $versionInfo, $matches))
            $valid[$matches[1]] = $interpreter;
    }

    // Handle easy cases
    if (count($valid) == 0)
        return null;
    if (count($valid) == 1)
        return reset($valid);

    // List available options for user
    krsort($valid);
    return reset($valid);
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
