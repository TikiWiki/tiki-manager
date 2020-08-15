<?php

namespace TikiManager\Command\Traits;

use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Access\Access;
use TikiManager\Access\FTP;
use TikiManager\Access\SSH;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\Environment;

trait InstanceConfigure
{
    /**
     * Configure Instance Access
     *
     * @param Instance $instance
     * @return Instance
     * @throws \Exception
     */
    public function setupAccess(Instance $instance): Instance
    {
        $type = $this->input->getOption('type');

        if (!$type) {
            $host = $this->input->getOption('host');
            $port = $this->input->getOption('port');
            $type = !$host ? 'local' : ($port === '22' ? 'ssh' : ($port === '21' ? 'ftp' : null));
        }

        $type = $this->io->choice('Connection type', CommandHelper::supportedInstanceTypes(), $type);
        $instance->type = $type;

        $access = $instance->getBestAccess();

        if ($type !== 'local') {
            $access = $this->setupRemoteAccess($access);
        }

        $discovery = $instance->getDiscovery();

        if ($type === 'local') {
            $access->host = 'localhost';
            $access->user = $discovery->detectUser();
        }

        // We need to save here or else SQLite overrides other instance
        // if match some other details.
        // This save() should check for the id and use Insert or Update depending on the value
        $instance->save();
        $access->save();

        return $instance;
    }

    /**
     * @param Access $access
     * @return Access|FTP|SSH
     * @throws \Exception
     */
    protected function setupRemoteAccess(Access $access)
    {
        $host = $this->io->ask('Host', $this->input->getOption('host') ?? '', function ($value) {
            return !empty($value) ? $value : new InvalidOptionException("You must provide a valid remote host. Please use --host=<HOST>");
        });

        $access->host = $host;

        $defaultPort = $this->input->getOption('port') ?? ($access instanceof SSH ? 22 : 21);
        $port = $this->io->ask('Port', $defaultPort, function ($value) {
            if (empty($value) || !is_numeric($value)) {
                throw new InvalidOptionException("You must provide a valid remote port number. Please use --port=<PORT>");
            }
            return $value;
        });

        $access->port = $port;

        $user = $this->input->getOption('user') ?? '';
        $user = $this->io->ask('User', $user, function ($value) {
            if (empty($value)) {
                throw new InvalidOptionException("You must provide a valid remote user. Please use --user=<USER>");
            }
            return $value;
        });

        $access->user = $user;

        $pass = null;
        if ($access instanceof FTP) {
            $passValidator = function ($value) {
                if (empty($value)) {
                    throw new InvalidOptionException("You must provide a valid remote password. Please use --pass=<PASS>");
                }
            };

            $question = new Question('Pass', $pass = $this->input->getOption('pass') ?? '');
            $question->setValidator($passValidator);
            $question->setHidden(true);
            $question->setHiddenFallback(true);
            $pass = $this->io->askQuestion($question);
        }

        $access->password = $pass;

        if (!$access->firstConnect()) {
            throw new \Exception();
        }

        return $access;
    }

    /**
     * @param Instance $instance
     * @return Instance
     * @throws \TikiManager\Application\Exception\ConfigException
     */
    public function setupInstance(Instance $instance) : Instance
    {
        $url = $this->input->getOption('url') ?: $instance->getDiscovery()->detectWeburl();
        $url = $this->io->ask('WebUrl', $url, function ($value) {
            if (empty($value)) {
                throw new InvalidOptionException('URL cannot be empty. Please use --url=<URL>');
            }

            if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                throw new InvalidOptionException('URL is invalid. Please use --url=<URL>');
            }

            return $value;
        });

        $instance->weburl = $url;

        $name = $this->input->getOption('name') ?: $instance->getDiscovery()->detectName();
        $name = $this->io->ask('Name', $name, function ($value) {
            if (empty($value)) {
                throw new InvalidOptionException('Name cannot be empty. Please use --name=<NAME>');
            }

            return $value;
        });

        $instance->name = $name;

        $email = $this->input->getOption('email');
        $email = $this->io->ask('Email', $email, function ($value) {
            if ($value && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidOptionException('Please insert a valid email address. Please use --email=<EMAIL>');
            }

            return $value;
        });

        $instance->contact = $email;

        // PATHS
        $access = $instance->getBestAccess();

        $webRoot = $this->input->getOption('webroot') ?? $instance->getDiscovery()->detectWebroot();
        $webRoot = $this->io->ask('WebRoot', $webRoot, function ($value) use ($access) {
            if (empty($value)) {
                throw new InvalidOptionException('WebRoot cannot be empty. Please use --webroot=<PATH>');
            }

            $pathExists = $access->fileExists($value);
            $createDir = !$pathExists ? $this->io->confirm('Create directory?', true) : false;

            if ($createDir && !$access->createDirectory($value)) {
                throw new \Exception('Unable to create the directory: ' . $value);
            }

            if (!$pathExists && !$createDir) {
                throw new \Exception('Directory not created');
            }

            return $value;
        });

        $tempDir = $this->input->getOption('tempdir') ?? Environment::get('TRIM_TEMP') ?? '';
        $tempDir = $this->io->ask('TempDir', $tempDir, function ($value) use ($access) {
            if (empty($value)) {
                throw new InvalidOptionException('TempDir cannot be empty. Please use --tempDir=<PATH>');
            }

            $pathExists = $access->fileExists($value);
            $createDir = !$pathExists ? $this->io->confirm('Create directory?', true) : false;

            if ($createDir && !$access->createDirectory($value)) {
                throw new \Exception('Unable to create the directory: ' . $value);
            }

            if (!$pathExists && !$createDir) {
                throw new \Exception('Directory not created');
            }

            return $value;
        });

        $instance->webroot = $webRoot;
        $instance->tempdir = $tempDir;
        $instance->phpexec = $instance->getDiscovery()->detectPHP();
        $instance->phpversion = $instance->getDiscovery()->detectPHPVersion();

        //
        // Backups
        //
        $backupDir = Environment::get('BACKUP_FOLDER');
        $defaultBackupUser = posix_getpwuid(fileowner($backupDir));
        $defaultBackupGroup = posix_getgrgid(filegroup($backupDir));
        $defaultBackupPerm = sprintf('%o', fileperms($backupDir) & 0777);

        $backupUser = $this->input->getOption('backup-user') ?? $defaultBackupUser['name'] ?? '';
        $backupGroup = $this->input->getOption('backup-group') ?? $defaultBackupGroup['name'] ?? '';
        $backupPerm = $this->input->getOption('backup-permission') ?? $defaultBackupPerm ?? '';

        $backupUser = $this->io->ask('Backup user', $backupUser, function ($value) {
            if (!$value) {
                throw new InvalidOptionException('Backup user cannot be empty. Please use --backup-user=<USER>');
            }

            return $value;
        });

        $backupGroup = $this->io->ask('Backup group', $backupGroup, function ($value) {
            if (!$value) {
                throw new InvalidOptionException('Backup group cannot be empty. Please use --backup-group=<GROUP>');
            }

            return $value;
        });

        $backupPerm = $this->io->ask('Backup file permissions', $backupPerm, function ($value) {
            if (!$value || !is_numeric($value)) {
                throw new InvalidOptionException('Backup file permissions must be numeric. Please use --backup-permission=<PERM>');
            }

            return $value;
        });

        $instance->backup_user = $backupUser;
        $instance->backup_group = $backupGroup;
        $instance->backup_perm = octdec($backupPerm);

        return $instance;
    }

    /**
     * Setup Tiki Application details (branch).
     * @param Instance $instance
     * @return Instance
     * @throws \Exception
     */
    public function setupApplication(Instance $instance): Instance
    {
        if ($this->input->getOption('blank')) {
            $instance->selection = 'blank : none';
            return $instance;
        }

        $this->io->writeln('Fetching compatible versions. Please wait...');

        if ($this->input->isInteractive() || $this->io->isVeryVerbose()) {
            $this->io->note(
                "If some versions are not offered, it's likely because the host " .
                "server doesn't meet the requirements for that version (ex: PHP version is too old)"
            );
        }

        $default = null;
        $branchName = $this->input->getOption('branch');

        $versions = [];
        foreach ($instance->getCompatibleVersions() as $version) {
            if ($version instanceof Version && $version->branch == $branchName) {
                $default = (string)$version;
            }
            $versions[] = (string)$version;
        }

        $selection = $this->io->choice('Branch', $versions, $default);

        if (!$selection) {
            // Running non interactively the selection will be null if not matched
            throw new \Exception('Selected branch not found.');
        }

        $instance->selection = $selection;

        return $instance;
    }

    /**
     * Check, configure and test database connection for a given instance
     * It does not make any changes
     *
     * @param Instance $instance
     * @return Instance
     * @throws \Exception
     */
    public function setupDatabase(Instance $instance): Instance
    {
        if ($instance->selection == 'blank : none') {
            return $instance;
        }

        $dbRoot = $instance->getDatabaseConfig();
        if ($dbRoot && $dbRoot->testConnection()) {
            // Instance has a working database connection
            return $instance;
        }

        $this->io->note('Creating databases and users requires root privileges on MySQL.');

        $dbRoot = $instance->database();

        if ($instance->type == 'local') {
            $defaultHost = $_ENV['DB_HOST'] ?? 'localhost';
            $defaultUser = $_ENV['DB_USER'] ?? 'root';
            $defaultPass = $_ENV['DB_PASS'] ?? null;
        } else {
            $defaultHost = 'localhost';
            $defaultUser = 'root';
            $defaultPass = null;
        }

        $dbRoot->host = $this->input->hasOption('db-host') ? ($this->input->getOption('db-host') ?: $defaultHost) : $defaultHost;
        $dbRoot->user = $this->input->hasOption('db-user') ? ($this->input->getOption('db-user') ?: $defaultUser) : $defaultUser;
        $dbRoot->pass = $this->input->hasOption('db-pass') ? ($this->input->getOption('db-pass') ?: $defaultPass) : $defaultPass;

        while (!$dbRoot->testConnection()) {
            if (!$this->input->isInteractive()) {
                throw new \Exception('Unable to access database with administrative privileges');
            }

            $dbRoot->host = $this->io->ask('Database host', $dbRoot->host);
            $dbRoot->user = $this->io->ask('Database user', $dbRoot->user);
            $dbRoot->pass = $this->io->askHidden('Database password');
        }

        $this->io->writeln('<info>Connected to MySQL with administrative privileges</info>');

        $hasPrefix = $this->input->hasOption('db-prefix') ? $this->input->getOption('db-prefix') : null;
        $dbName = $this->input->hasOption('db-name') ? $this->input->getOption('db-name') : null;

        if (!$hasPrefix && ($dbName || !$this->io->confirm('Should a new database and user be created now (both)?'))) {
            $dbRoot->dbname = $this->io->ask('Database name', $dbName ?? 'tiki_db');
        } else {
            $dbPrefix = $this->input->hasOption('db-prefix') ? ($this->input->getOption('db-prefix') ?: 'tiki') : 'tiki';

            $dbPrefix = $this->io->ask(
                'Prefix to use for username and database',
                $dbPrefix,
                function ($prefix) use ($dbRoot, &$dbPrefix) {

                    $maxPrefixLength = $dbRoot->getMaxUsernameLength() - 5;

                    if (strlen($prefix) > $maxPrefixLength) {
                        $dbPrefix = substr($prefix, 0, $maxPrefixLength);
                        throw new \Exception("Prefix is a string with maximum of {$maxPrefixLength} chars");
                    }

                    $username = "{$prefix}_user";
                    if ($dbRoot->userExists($username)) {
                        throw new \Exception("User '$username' already exists, can't proceed.");
                    }

                    $dbname = "{$prefix}_db";
                    if ($dbRoot->databaseExists($dbname)) {
                        $this->io->warning("Database '$dbname' already exists.");
                        if (!$this->io->confirm('Continue?')) {
                            return false;
                        }
                    }

                    return $prefix;
                }
            );
        }

        $config = [
            'host' => $dbRoot->host,
            'user' => $dbRoot->user,
            'pass' => $dbRoot->pass,
            'database' => $dbRoot->dbname ?: null,
            'prefix' => isset($dbPrefix) ? $dbPrefix : null
        ];

        $instance->setDatabaseConfig($config);

        return $instance;
    }

    /**
     * Persist the instance information and install the application
     *
     * @param Instance $instance
     * @return void
     * @throws \TikiManager\Application\Exception\ConfigException
     */
    public function install(Instance $instance)
    {
        $checksumCheck = $this->input->getOption('check') ?? false;

        $discovery = $instance->getDiscovery();
        $instance->vcs_type = $type = $discovery->detectVcsType();
        $instance->phpexec = $discovery->detectPHP();
        $instance->phpversion = $discovery->detectPHPVersion();
        $instance->save();
        // Save access details
        $instance->getBestAccess()->save();

        $this->io->writeln('<info>Instance information saved.</info>', OutputInterface::VERBOSITY_VERBOSE);

        if ($instance->selection == 'blank : none') {
            $this->io->success('This is a blank instance. This is useful to restore a backup later.');
            return;
        }

        $apps = $instance->getApplications();
        // Tiki is the only supported application
        $app = reset($apps);
        $isInstalled = $app->isInstalled();

        if ($isInstalled) {
            if (!$instance->hasDuplicate()) {
                $add = $this->io->confirm(
                    'An application was detected in [' . $instance->webroot . '], do you want add it to the list?:',
                    $this->input->isInteractive() // TRUE is interactive, false otherwise
                );

                if (!$add) {
                    $instance->delete();
                    throw new \Exception('Unable to install. An application was detected in this instance.');
                }

                $result = $app->registerCurrentInstallation();
                $resultInstance = $result->getInstance();

                if ($instance->id === $resultInstance->id) {
                    $this->io->success('Please test your site at ' . $instance->weburl);
                    return;
                }
            }

            $instance->delete();
            throw new \Exception('Unable to install. An application was detected in this instance.');
        }

        $selection = $instance->selection;
        $details = array_map('trim', explode(':', $selection));
        $version = Version::buildFake($details[0], $details[1]);

        $this->io->writeln('Installing ' . $app->getName() . '... <fg=yellow>[may take a while]</>');

        $this->io->note(
            'If for any reason the installation fails (ex: wrong setup.sh parameters for tiki), ' .
            'you can use \'tiki-manager instance:access\' to complete the installation manually.'
        );

        $instance->installApplication($app, $version, $checksumCheck);

        $this->io->success('Please test your site at ' . $instance->weburl);
    }
}
