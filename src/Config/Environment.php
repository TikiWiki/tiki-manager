<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */
namespace TikiManager\Config;

use Phar;
use Symfony\Component\Dotenv\Dotenv;
use TikiManager\Config\Exception\ConfigurationErrorException;
use TikiManager\Libs\Helpers\PDOWrapper;
use TikiManager\Libs\Requirements\Requirements;

/**
 * Class Environment
 * Main class to handle Environment related operations. This includes loading, setting and/or overwriting environment variables.
 * @package TikiManager\Config
 */
class Environment
{
    private $homeDirectory;
    private $isLoaded = false;

    /**
     * Environment constructor.
     * @param $homeDirectory
     */
    public function __construct($homeDirectory)
    {
        $this->homeDirectory = $homeDirectory;
        require_once $this->homeDirectory . '/src/Libs/Helpers/functions.php';
    }

    /**
     * Main function to load an environment. This load takes in consideration .env.dist, .env and such.
     * @throws ConfigurationErrorException
     */
    public function load()
    {
        $dotenvLoader = new Dotenv();
        $envDistFile = $this->homeDirectory . '/.env.dist';
        $envFile = $this->homeDirectory . '/.env';

        if (! file_exists($envDistFile)) {
            throw new ConfigurationErrorException('.env.dist file not found at: "' . $envDistFile . '"');
        }

        $this->setRequiredEnvironmentVariables();

        $dotenvLoader->load($envDistFile);
        $dotenvLoader->loadEnv($envFile);

        $this->loadEnvironmentVariablesContainingLogic();
        $this->initializeComposerEnvironment();
        $this->runSetup();
        $this->isLoaded = true;
    }

    /**
     * Load environment variables that contain any kind of logic
     */
    private function loadEnvironmentVariablesContainingLogic()
    {
        $_ENV['TRIM_OS'] = strtoupper(substr(PHP_OS, 0, 3));

        if ($_ENV['TRIM_OS'] === 'WIN') {
            $_ENV['INTERACTIVE'] = php_sapi_name() === 'cli' && getenv('NONINTERACTIVE') !== 'true';
        } else {
            $_ENV['INTERACTIVE'] = php_sapi_name() === 'cli'
                && getenv('NONINTERACTIVE') !== 'true'
                && !in_array(getenv('TERM'), ['dumb', false, ''])
                && preg_match(',^/dev/,', exec('tty'));
        }

        if (file_exists(getenv('HOME') . '/.ssh/id_rsa') &&
            file_exists(getenv('HOME') . '/.ssh/id_rsa.pub')) {
            $_ENV['SSH_KEY'] = getenv('HOME') . '/.ssh/id_rsa';
            $_ENV['SSH_PUBLIC_KEY'] = getenv('HOME') . '/.ssh/id_rsa.pub';
        }

        if (!isset($_ENV['SSH_KEY']) && !isset($_ENV['SSH_PUBLIC_KEY'])) {
            $_ENV['SSH_KEY'] = $_ENV['TRIM_ROOT'] . "/data/id_rsa";
            $_ENV['SSH_PUBLIC_KEY'] = $_ENV['TRIM_ROOT'] . "/data/id_rsa.pub";
        }

        if (! isset($_ENV['EDITOR'])) {
            $_ENV['EDITOR'] = 'nano';
        }

        if (! isset($_ENV['DIFF'])) {
            $_ENV['DIFF'] = 'diff';
        }

        $localComposerPath = $this->homeDirectory . '/composer.phar';
        if (file_exists($localComposerPath)) {
            $_ENV['COMPOSER_PATH'] = $localComposerPath;
        } elseif (Requirements::getInstance()->hasDependency('composer')) {
            $_ENV['COMPOSER_PATH'] = 'composer';
        } else {
            throw new ConfigurationErrorException('Unable to find composer or composer.phar');
        }

        $_ENV['EXECUTABLE_SCRIPT'] = implode(',', [
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

        $_ENV['TRIM_TEMP'] = '/tmp/trim_temp';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $_ENV['TRIM_TEMP'] = getenv('TEMP') . "\\trim_temp";
        }
    }

    /**
     * Sets required environment variables that are used within .env files
     */
    private function setRequiredEnvironmentVariables()
    {
        $pharPath = Phar::running(false);

        $_ENV['TRIM_ROOT'] = isset($pharPath) && !empty($pharPath) ? realpath(dirname($pharPath)) : $this->homeDirectory;
        $_ENV['IS_PHAR'] = isset($pharPath) && !empty($pharPath);
    }

    /**
     * Initialize composer related operations and autoload
     * Load autoload afterwards
     */
    private function initializeComposerEnvironment()
    {
        if (! $_ENV['IS_PHAR']) {
            run_composer_install();
        }

        require_once $this->homeDirectory . '/vendor/autoload.php';
    }

    /**
     * Logic to setup the environment
     * Moved from env_setup.php
     */
    private function runSetup()
    {
        debug('Running Tiki Manager at ' . $_ENV['TRIM_ROOT']);

        if (! file_exists($_ENV['CACHE_FOLDER'])) {
            mkdir($_ENV['CACHE_FOLDER'], 0777, true);
        }
        if (! file_exists($_ENV['TEMP_FOLDER'])) {
            mkdir($_ENV['TEMP_FOLDER'], 0777, true);
        }
        if (! file_exists($_ENV['RSYNC_FOLDER'])) {
            mkdir($_ENV['RSYNC_FOLDER'], 0777, true);
        }
        if (! file_exists($_ENV['MOUNT_FOLDER'])) {
            mkdir($_ENV['MOUNT_FOLDER'], 0777, true);
        }
        if (! file_exists($_ENV['BACKUP_FOLDER'])) {
            mkdir($_ENV['BACKUP_FOLDER'], 0777, true);
        }
        if (! file_exists($_ENV['ARCHIVE_FOLDER'])) {
            mkdir($_ENV['ARCHIVE_FOLDER'], 0777, true);
        }
        if (! file_exists($_ENV['TRIM_LOGS'])) {
            mkdir($_ENV['TRIM_LOGS'], 0777, true);
        }
        if (! file_exists($_ENV['TRIM_DATA'])) {
            mkdir($_ENV['TRIM_DATA'], 0777, true);
        }
        if (! file_exists($_ENV['TRIM_SRC_FOLDER'])) {
            mkdir($_ENV['TRIM_SRC_FOLDER'], 0777, true);
        }

        if (file_exists(getenv('HOME') . '/.ssh/id_dsa') &&
            file_exists(getenv('HOME') . '/.ssh/id_dsa.pub') &&
            !isset($_ENV['SSH_KEY']) &&
            !isset($_ENV['SSH_PUBLIC_KEY'])) {
            warning(
                sprintf(
                    'Ssh-dsa key (%s and %s) was found but Tiki Manager won\'t used it, ' .
                    'because DSA was deprecated in openssh-7.0. ' .
                    'If you need a new RSA key, run \'tiki-manager instance:copysshkey\' and Tiki Manager will create a new one.' .
                    'Copy the new key to all your instances.',
                    $_ENV['SSH_KEY'],
                    $_ENV['SSH_PUBLIC_KEY']
                )
            );
        }

        if (file_exists($_ENV['TRIM_ROOT'] . "/data/id_dsa") &&
            file_exists($_ENV['TRIM_ROOT'] . "/data/id_dsa.pub") &&
            !isset($_ENV['SSH_KEY']) &&
            !isset($_ENV['SSH_PUBLIC_KEY'])) {
            warning(
                sprintf(
                    'Ssh-dsa key (%s and %s) was found but Tiki Manager won\'t used it, ' .
                    'because DSA was deprecated in openssh-7.0. ' .
                    'If you need a new RSA key, run \'make copysshkey\' and Tiki Manager will create a new one.' .
                    'Copy the new key to all your instances.',
                    $_ENV['SSH_KEY'],
                    $_ENV['SSH_PUBLIC_KEY']
                )
            );
        }

        if (! Requirements::getInstance()->check('PHPSqlite')) {
            error(Requirements::getInstance()->getRequirementMessage('PHPSqlite'));
            exit;
        }

        if (! Requirements::getInstance()->check('ssh')) {
            error(Requirements::getInstance()->getRequirementMessage('ssh'));
            exit;
        }

        if (strtoupper($_ENV['DEFAULT_VCS']) === 'SRC') {
            if (! Requirements::getInstance()->check('fileCompression')) {
                error(Requirements::getInstance()->getRequirementMessage('fileCompression'));
                exit;
            }
        }

        if (! file_exists($_ENV['SSH_KEY']) || ! file_exists($_ENV['SSH_PUBLIC_KEY'])) {
            if (! is_writable(dirname($_ENV['SSH_KEY']))) {
                die(error('Impossible to generate SSH key. Make sure data folder is writable.'));
            }

            echo 'If you enter a passphrase, you will need to enter it every time you run ' .
                'Tiki Manager, and thus, automatic, unattended operations (like backups, file integrity ' .
                "checks, etc.) will not be possible.\n";

            $key = $_ENV['SSH_KEY'];
            `ssh-keygen -t rsa -f $key`;
        }

        if ($_ENV['IS_PHAR']) {
            setupPhar();
        }

        global $db;

        if (! file_exists($_ENV['DB_FILE'])) {
            if (! is_writable(dirname($_ENV['DB_FILE']))) {
                die(error('Impossible to generate database. Make sure data folder is writable.'));
            }

            try {
                $db = new PDOWrapper('sqlite:' . $_ENV['DB_FILE']);
            } catch (\PDOException $e) {
                die(error("Could not create the database for an unknown reason. SQLite said: {$e->getMessage()}"));
            }

            $db->exec('CREATE TABLE info (name VARCHAR(10), value VARCHAR(10), PRIMARY KEY(name));');
            $db->exec("INSERT INTO info (name, value) VALUES('version', '0');");
            $db = null;

            $file = $_ENV['DB_FILE'];
        }

        try {
            $db = new PDOWrapper('sqlite:' . $_ENV['DB_FILE']);
        } catch (\PDOException $e) {
            die(error("Could not connect to the database for an unknown reason. SQLite said: {$e->getMessage()}"));
        }

        $result = $db->query("SELECT value FROM info WHERE name = 'version'");
        $version = (int)$result->fetchColumn();
        unset($result);

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
            // no break
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
            // no break
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
            // no break
            case 3:
                $db->exec("
                    UPDATE access SET host = (host || ':' || '22') WHERE type = 'ssh';
                    UPDATE access SET host = (host || ':' || '22') WHERE type = 'ssh::nokey';
                    UPDATE access SET host = (host || ':' || '21') WHERE type = 'ftp';
            
                    UPDATE info SET value = '4' WHERE name = 'version';
                ");
            // no break
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
            // no break
            case 5:
                $db->exec("
                    ALTER TABLE version ADD COLUMN revision VARCHAR(25);
                    UPDATE info SET value = '6' WHERE name = 'version';
                ");
            // no break
        }
    }
}
