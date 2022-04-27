<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */
namespace TikiManager\Config;

use Phar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;
use TikiManager\Libs\Helpers\PDOWrapper;
use Symfony\Component\Console\Input\ArgvInput;
use TikiManager\Libs\Requirements\Requirements;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use TikiManager\Config\Exception\ConfigurationErrorException;
use TikiManager\Style\TikiManagerStyle;

/**
 * Class Environment
 * Main class to handle Environment related operations. This includes loading, setting and/or overwriting environment variables.
 * @package TikiManager\Config
 */
class Environment
{
    private $homeDirectory;
    private $isLoaded = false;

    private static $instance;

    protected $io;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->homeDirectory = dirname(dirname(__DIR__));
        $this->setIO();
        require_once $this->homeDirectory . '/src/Libs/Helpers/functions.php';
    }

    public function setIO(InputInterface $input = null, OutputInterface $output = null)
    {
        if (!$input) {
            $input = new ArgvInput();
            if (PHP_SAPI != 'cli') {
                $input->setInteractive(false);
            }
        }

        if (!$output) {
            $output = PHP_SAPI != 'cli' ? new StreamOutput(fopen('php://output', 'w')) : new ConsoleOutput();

            if (PHP_SAPI != 'cli') {
                $formatter = App::get('ConsoleHtmlFormatter');
                $output->setFormatter($formatter);
            }
        }

        $container = App::getContainer();
        $container->set('input', $input);
        $container->set('output', $output);

        $container->set('io', new TikiManagerStyle($input, $output));

        $this->io = $container->get('io');
    }

    /**
     * Main function to load an environment. This load takes in consideration .env.dist, .env and such.
     * @throws ConfigurationErrorException
     */
    public function load()
    {
        $this->setRequiredEnvironmentVariables();

        $dotenvLoader = new Dotenv();
        $envDistFile = $this->homeDirectory . '/.env.dist';

        if (!file_exists($envDistFile)) {
            throw new ConfigurationErrorException('.env.dist file not found at: "' . $envDistFile . '"');
        }

        $envFile = static::get('TM_DOTENV');
        $dotenvLoader->load($envDistFile);
        $dotenvLoader->loadEnv($envFile);

        $this->loadEnvironmentVariablesContainingLogic();
        $this->runSetup();
        $this->isLoaded = true;
    }

    public function setComposerPath($composerPath)
    {
        if (file_exists($composerPath)) {
            $_ENV['COMPOSER_PATH'] = $composerPath;
        } else {
            throw new ConfigurationErrorException('Invalid composer path specified: '.$composerPath);
        }
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
            $_ENV['SSH_KEY'] = $_ENV['TRIM_DATA'] . "/id_rsa";
            $_ENV['SSH_PUBLIC_KEY'] = $_ENV['TRIM_DATA'] . "/id_rsa.pub";
        }

        if (! isset($_ENV['EDITOR'])) {
            $_ENV['EDITOR'] = 'nano';
        }

        if (! isset($_ENV['DIFF'])) {
            $_ENV['DIFF'] = 'diff';
        }

        if (empty($_ENV['COMPOSER_PATH'])) {
            $composerPath = detectComposer($this->homeDirectory);
            if (!$composerPath) {
                throw new ConfigurationErrorException('Unable to find composer or composer.phar');
            }
            $_ENV['COMPOSER_PATH'] = $composerPath;
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

        $_ENV['INSTANCE_WORKING_TEMP'] = static::generateUniqueWorkingDirectoryForInstance();
    }

    /**
     * Generate a unique directory name that can be used as working directory (temporary) for an instance
     *
     * @return string
     */
    protected static function generateUniqueWorkingDirectoryForInstance(): string
    {
        // to generate a smaller unique suffix, we use the time in seconds since 1st Jan 2020 and 3 random digits at the end
        // since this was added late 2020, all suffix will be unique
        $secondsSinceJan2020 = time() - 1577836800;
        return sprintf("tiki_mgr_%d%03d", $secondsSinceJan2020, rand(0, 999));
    }

    /**
     * Sets required environment variables that are used within .env files
     */
    private function setRequiredEnvironmentVariables()
    {
        $pharPath = Phar::running(false);

        $_ENV['IS_PHAR'] = $isPhar = isset($pharPath) && !empty($pharPath);

        $rootPath = ($isPhar ? realpath(dirname($pharPath)) : $this->homeDirectory);
        $_ENV['TRIM_ROOT'] = $_ENV['TRIM_ROOT'] ?? $rootPath;
        $_ENV['TM_DOTENV'] = $rootPath . '/.env';
    }

    /**
     * Logic to setup the environment
     * Moved from env_setup.php
     */
    private function runSetup()
    {
        debug('Running Tiki Manager at ' . $_ENV['TRIM_ROOT']);

        $writableFolders = ['CACHE_FOLDER', 'TEMP_FOLDER', 'RSYNC_FOLDER', 'MOUNT_FOLDER', 'BACKUP_FOLDER', 'ARCHIVE_FOLDER', 'TRIM_LOGS', 'TRIM_DATA', 'TRIM_SRC_FOLDER'];
        foreach ($writableFolders as $folder) {
            if (! file_exists($_ENV[$folder])) {
                if (is_writable(dirname($_ENV[$folder]))) {
                    mkdir($_ENV[$folder], 0777, true);
                }
            } elseif (substr(sprintf('%o', fileperms($_ENV[$folder])), -4) != '0777') {
                @chmod($_ENV[$folder], 0777);
            }
        }

        if (file_exists(getenv('HOME') . '/.ssh/id_dsa') &&
            file_exists(getenv('HOME') . '/.ssh/id_dsa.pub') &&
            !isset($_ENV['SSH_KEY']) &&
            !isset($_ENV['SSH_PUBLIC_KEY'])) {
            $this->io->warning(
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

        if (file_exists($_ENV['TRIM_DATA'] . "/id_dsa") &&
            file_exists($_ENV['TRIM_DATA'] . "/id_dsa.pub") &&
            !isset($_ENV['SSH_KEY']) &&
            !isset($_ENV['SSH_PUBLIC_KEY'])) {
            $this->io->warning(
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
            throw new ConfigurationErrorException(Requirements::getInstance()->getRequirementMessage('PHPSqlite'));
        }

        if (! Requirements::getInstance()->check('ssh')) {
            throw new ConfigurationErrorException(Requirements::getInstance()->getRequirementMessage('ssh'));
        }

        if (strtoupper($_ENV['DEFAULT_VCS']) === 'SRC') {
            if (! Requirements::getInstance()->check('fileCompression')) {
                throw new ConfigurationErrorException(Requirements::getInstance()->getRequirementMessage('fileCompression'));
            }
        }

        if (! file_exists($_ENV['SSH_KEY']) || ! file_exists($_ENV['SSH_PUBLIC_KEY'])) {
            if (! is_writable(dirname($_ENV['SSH_KEY']))) {
                throw new ConfigurationErrorException('Impossible to generate SSH key. Make sure data folder is writable.');
            }

            $this->io->writeln('If you enter a passphrase, you will need to enter it every time you run ' .
                'Tiki Manager, and thus, automatic, unattended operations (like backups, file integrity ' .
                "checks, etc.) will not be possible.");

            $key = $_ENV['SSH_KEY'];
            `ssh-keygen -t rsa -f $key`;
        }

        if ($_ENV['IS_PHAR']) {
            setupPhar();
        }

        global $db;

        if (! file_exists($_ENV['DB_FILE'])) {
            if (! is_writable(dirname($_ENV['DB_FILE']))) {
                throw new ConfigurationErrorException('Impossible to generate database. Make sure data folder is writable.');
            }

            try {
                $db = new PDOWrapper('sqlite:' . $_ENV['DB_FILE']);
                chmod($_ENV['DB_FILE'], 0666);
            } catch (\PDOException $e) {
                throw new ConfigurationErrorException("Could not create the database for an unknown reason. SQLite said: {$e->getMessage()}");
            }
            $db = null;

            $file = $_ENV['DB_FILE'];
        }

        try {
            $db = new PDOWrapper('sqlite:' . $_ENV['DB_FILE']);
        } catch (\PDOException $e) {
            throw new ConfigurationErrorException("Could not connect to the database for an unknown reason. SQLite said: {$e->getMessage()}");
        }

        // check if info table exist or create it
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='info';");
        $infoTableName = (string)$result->fetchColumn();
        unset($result);
        if ($infoTableName !== 'info') {
            $db->exec('CREATE TABLE info (name VARCHAR(10), value VARCHAR(10), PRIMARY KEY(name));');
            $db->exec("INSERT INTO info (name, value) VALUES('version', '0');");
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
            case 6:
                $db->exec("
                    UPDATE instance SET name=(name || '-' || instance_id)
                        WHERE name IN (
                            SELECT name FROM instance
                                GROUP BY name
                                HAVING COUNT(name) > 1
                        );
                    CREATE UNIQUE INDEX idx_unique_name ON instance(name);
                    UPDATE info SET value = '7' WHERE name = 'version';
                ");
            // no break
            case 7:
                $db->exec("
                    ALTER TABLE version ADD COLUMN action VARCHAR(25);
                    UPDATE info SET value = '8' WHERE name = 'version';
                ");
            // no break
            case 8:
                $db->exec("
                    INSERT INTO info (name, value) VALUES ('login_attempts', 0);
                    UPDATE info SET value = '9' WHERE name = 'version';
                ");
            // no break
            case 9:
                $db->exec("
                    CREATE TABLE patch (
                        patch_id INTEGER PRIMARY KEY,
                        instance_id INTEGER,
                        package VARCHAR(100),
                        url VARCHAR(255),
                        date VARCHAR(25)
                    );
                    UPDATE info SET value = '10' WHERE name = 'version';
                ");
            // no break
        }
    }

    public static function get($key, $defaultValue = null)
    {
        $variables = self::getInstance()->getEnvironmentVariables();

        return isset($variables[$key]) ? $variables[$key] : $defaultValue;
    }

    /**
     * Get a merge of environment variables $_ENV and $_SERVER.
     *
     * @return array
     */
    protected function getEnvironmentVariables()
    {
        return array_merge($_ENV, $_SERVER);
    }
}
