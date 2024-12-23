<?php

namespace TikiManager\Command\Traits;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use TikiManager\Access\Access;
use TikiManager\Access\FTP;
use TikiManager\Access\SSH;
use TikiManager\Application\Instance;
use TikiManager\Application\Tiki;
use TikiManager\Application\Version;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Config\Environment as Env;

trait InstanceConfigure
{
    use LoggerAwareTrait;

    public function printManagerInfo()
    {
        if ($this->io->getVerbosity() != OutputInterface::VERBOSITY_DEBUG &&
            !Env::get('TRIM_DEBUG', false)) {
            return;
        }

        $this->io->title('Tiki Manager Info');
        $mockInstance = new Instance();
        $mockInstance->type = 'local';
        $mockDiscovery = $mockInstance->getDiscovery();

        CommandHelper::displayInfo($mockDiscovery);
    }

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
    public function setupInstance(Instance $instance, $import = false) : Instance
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
            if (is_numeric($value)) {
                throw new InvalidOptionException('Name cannot be a numerical value (otherwise we can\'t differenciate from ID).');
            }

            global $db;
            $query = "SELECT COUNT(*) as numInstances FROM instance WHERE name = :name";
            $stmt = $db->prepare($query);
            $stmt->execute([':name' => $value]);
            $count = $stmt->fetchObject();

            if ($count->numInstances) {
                throw new InvalidOptionException('Instance name already in use. Please choose another name.');
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
        $webRoot = $this->io->ask('WebRoot', $webRoot, function ($value) use ($access, $instance, $import) {
            if (empty($value)) {
                throw new InvalidOptionException('WebRoot cannot be empty. Please use --webroot=<PATH>');
            }

            $pathExists = $access->fileExists($value);

            $force = $this->input->hasOption('force') && $this->input->getOption('force');

            if ($pathExists && ($force || $access->isEmptyDir($value) || $import)) {
                return $value;
            }

            $instance->webroot = $value;

            // Check if webroot has contents and it's not a Tiki Instance
            if ($pathExists && !$this->detectApplication($instance)) {
                return $this->handleNotEmptyWebrootFolder($value);
            }

            if (!$pathExists && $import) {
                $error = sprintf('Unable to import. Chosen directory (%s) does not exist.', $value);
                throw new \Exception($error);
            }

            $createDir = !$pathExists ? $this->io->confirm('Create directory?', true) : false;

            if ($createDir && !$access->createDirectory($value)) {
                throw new \Exception('Unable to create the directory: ' . $value);
            }

            if (!$pathExists && !$createDir) {
                throw new \Exception('Directory not created');
            }

            return $value;
        });

        $tempDir = $this->input->getOption('tempdir') ?? $instance->getDiscovery()->detectTmp() . DS . Env::get('INSTANCE_WORKING_TEMP');
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

        //
        // Backups
        // Backups are stored in Tiki-Manager instance
        //
        $backupDir = Env::get('BACKUP_FOLDER');
        $mockInstance = new Instance();
        $mockInstance->type = 'local';
        $mockDiscovery = $mockInstance->getDiscovery();
        list($backupUser, $backupGroup, $backupPerm) = $mockDiscovery->detectBackupPerm($backupDir);

        $backupUser = $this->input->getOption('backup-user') ?? $backupUser ?? '';
        $backupGroup = $this->input->getOption('backup-group') ?? $backupGroup ?? '';
        $backupPerm = $this->input->getOption('backup-permission') ?? $backupPerm ?? '';

        $backupUser = $this->io->ask('Backup user (the local user that will be used as backup files owner)', $backupUser, function ($value) use ($mockDiscovery) {
            if (!$value) {
                throw new InvalidOptionException('Backup user cannot be empty. Please use --backup-user=<USER>');
            }
            if (! $mockDiscovery->userExists($value)) {
                throw new InvalidOptionException('Backup user does not exist on the local host.');
            }

            return $value;
        });

        $backupGroup = $this->io->ask('Backup group (the local group that will be used as backup files owner)', $backupGroup, function ($value) use ($mockDiscovery) {
            if (!$value) {
                throw new InvalidOptionException('Backup group cannot be empty. Please use --backup-group=<GROUP>');
            }
            if (! $mockDiscovery->groupExists($value)) {
                throw new InvalidOptionException('Backup group does not exist on the local host.');
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

        // PHP SECTION DETECTION
        // If there is a given path, set that, detectPHP will test if valid, if not detect others;
        $instance->phpexec = $this->input->getOption('phpexec');

        // Detect the PHP that best suits the selected branch
        $is_blank = $this->input->hasOption('blank') && $this->input->getOption('blank');
        if (! $is_blank && $branchName = $this->input->getOption('branch')) {
            $apps = $instance->getApplications();
            /** @var Tiki $tiki */
            $tiki = reset($apps);
            $requirements = $tiki->getTikiRequirementsHelper()->findByBranchName($branchName);
        }

        $instance->detectPHP($requirements ?? null);

        $this->io->info('Instance PHP Version: ' . CommandHelper::formatPhpVersion($instance->phpversion));
        $this->io->info('Instance PHP exec: ' . $instance->phpexec);

        $vcsRepoUrl = $this->input->hasOption('repo-url') ? $this->input->getOption('repo-url') : null;
        if (!$import) {
            $vcsRepoUrl ??= Env::get('GIT_TIKIWIKI_URI');
            // TODO: if $import===true, we should try to auto-detect if there is a git repo, and git the repo url using
            // a git command. For now we just add an empty value (unless the user want to enter it manually, see bellow).
        }

        if ($this->input->isInteractive()) {
            $vcsRepoUrl = $this->io->ask('Enter Git Repository URL', $vcsRepoUrl);
            if (empty($vcsRepoUrl)) {
                $vcsRepoUrl = null; // normalize the value if an empty string was passed in interactive mode.
            }
        }

        if (!($import && $vcsRepoUrl === null)) { // when importing, null is valid.
            if (empty($vcsRepoUrl) || filter_var($vcsRepoUrl, FILTER_VALIDATE_URL) === false) {
                throw new InvalidOptionException('Vcs url is invalid. Please use --repo-url=<URL>');
            }
        }

        $instance->repo_url = $vcsRepoUrl;
        if (!$import) {
            $instance->copy_errors = $this->input->getOption('copy-errors') ?: 'ask';
        }

        return $instance;
    }

    /**
     * @param Instance $instance
     * @return bool
     * @throws \Exception
     */
    public function detectApplication(Instance $instance): bool
    {
        $apps = $instance->getApplications();
        // Tiki is the only supported application
        $app = reset($apps);

        return $app->isInstalled();
    }

    /**
     * @param Instance $instance
     * @throws \Exception
     */
    public function importApplication(Instance $instance): Instance
    {
        if (!$this->detectApplication($instance)) {
            throw new \Exception('Unable to import. An application was not detected in this instance.');
        };

        $instance->app = 'tiki';

        $result = $instance->getApplication()->registerCurrentInstallation();
        $resultInstance = $result->getInstance();

        if ($instance->id !== $resultInstance->id) {
            throw new \Exception('An error occurred while registering instance/application details', 2);
        }

        return $resultInstance;
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
     * @param Instance $instance
     * @return bool
     * @throws \Exception
     */
    public function testExistingDbConnection(Instance $instance): bool
    {
        if ($instance->testDbConnection()) {
            $this->logger->notice('{instance}: Database connection succeeded.', ['instance' => $instance->name]);
            return true;
        }

        $this->logger->error('{instance}: Existing database connection failed to connect.', ['instance' => $instance->name]);
        return false;
    }

    /**
     * Check, configure and test database connection for a given instance
     * It does not make any changes
     *
     * @param Instance $instance
     * @param bool $reconfigure
     * @return Instance
     * @throws \Exception
     */
    public function setupDatabase(Instance $instance, $reconfigure = false): Instance
    {
        try {
            if (!$reconfigure && $this->testExistingDbConnection($instance)) {
                return $instance;
            }
        } catch (\Exception $e) {
            // Left empty on purpose
        }

        $this->io->section('Setup '.$instance->name.' database connection');

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

        $connected = $dbRoot->testConnection();

        if (!$connected && !$this->input->isInteractive()) {
            throw new \Exception('Unable to access database.');
        }

        if (!$connected) {
            $this->io->writeln('<comment>Note: Creating databases and users requires root privileges on MySQL.</comment>');
        }

        while (!$connected) {
            $dbRoot->host = $this->io->ask('Database host', $dbRoot->host);
            $dbRoot->user = $this->io->ask('Database user', $dbRoot->user);
            $dbRoot->pass = $this->io->askHidden('Database password');
            $connected = $dbRoot->testConnection();
        }

        $this->logger->notice('Connected to MySQL as ' . $dbRoot->user);

        $hasPrefix = $this->input->hasOption('db-prefix') ? $this->input->getOption('db-prefix') : null;
        $dbName = $this->input->hasOption('db-name') ? $this->input->getOption('db-name') : null;

        $canCreateUser = $dbRoot->hasCreateUserPermissions();
        $canCreateDB = $dbRoot->hasCreateDatabasePermissions();

        if (!$canCreateUser) {
            $this->logger->warning('MySQL user cannot create users.');
        }

        if (!$canCreateDB) {
            $this->logger->warning('MySQL user cannot create databases.');
        }

        $usePrefix = false;
        if (!$dbName && $canCreateUser && $canCreateDB) {
            $usePrefix = $hasPrefix ?: $this->io->confirm('Should a new database and user be created now (both)?');
        }

        if (!$usePrefix) {
            $dbRoot->dbname = $this->io->ask('Database name', $dbName ?? 'tiki_db', function ($dbname) use ($dbRoot, $canCreateDB) {
                if (!$dbRoot->databaseExists($dbname) && !$canCreateDB) {
                    throw new \Exception("Database does not exist and user cannot create.");
                }

                return $dbname;
            });
        } else {
            $dbPrefix = $this->input->hasOption('db-prefix') ? ($this->input->getOption('db-prefix') ?: 'tiki') : 'tiki';

            $dbPrefix = $this->io->ask(
                'Prefix to use for username and database',
                $dbPrefix,
                function ($prefix) use ($dbRoot, &$dbPrefix) {

                    $maxUsernameLength = $dbRoot->getMaxUsernameLength() ?: 32;
                    $maxPrefixLength = $maxUsernameLength - 5;

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
                        $this->logger->warning("Database '$dbname' already exists.");
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
     * @return Instance
     * @throws \TikiManager\Application\Exception\ConfigException
     */
    public function install(Instance $instance): Instance
    {
        $checksumCheck = $this->input->getOption('check') ?? false;
        $revision = $this->input->getOption('revision');
        $discovery = $instance->getDiscovery();
        $instance->vcs_type = $discovery->detectVcsType();
        $instance->detectPHP();
        $instance->save();
        // Save access details
        $instance->getBestAccess()->save();

        $this->io->writeln('<info>Instance information saved.</info>', OutputInterface::VERBOSITY_VERBOSE);

        if ($instance->selection == 'blank : none') {
            $this->io->success('This is a blank instance. This is useful to restore a backup later.');
            return $instance;
        }

        if ($this->detectApplication($instance)) {
            return $instance;
        }

        $apps = $instance->getApplications();
        // Tiki is the only supported application
        $app = reset($apps);

        $selection = $instance->selection;
        $details = array_map('trim', explode(':', $selection));
        $version = Version::buildFake($details[0], $details[1]);

        $this->io->writeln('Installing ' . $app->getName() . '... <fg=yellow>[may take a while]</>');

        $this->io->note(
            'If for any reason the installation fails (ex: wrong setup.sh parameters for tiki), ' .
            'you can use \'tiki-manager instance:access\' to complete the installation manually.'
        );

        $instance->installApplication($app, $version, $checksumCheck, $revision);

        $this->io->success('Please test your site at ' . $instance->weburl);

        return $instance;
    }

    /**
     * @param string $path
     * @return string
     * @throws \Exception
     */
    public function handleNotEmptyWebrootFolder(string $path): string
    {
        $message = sprintf('Target webroot folder [%s] is not empty.', $path);
        if (!$this->input->isInteractive()) {
            $error = $message . ' Please select an empty webroot folder or use --force option to delete existing files.';
            throw new \Exception($error);
        }

        $this->io->warning($message);
        $confirm = $this->io->confirm("Installing a new Tiki instance, all files will be deleted.\n Do you want to continue?", false);

        if (!$confirm) {
            throw new \Exception($message . ' Select a different path.');
        }

        return $path;
    }

    /**
     * Check tiki minimum requirements
     *
     * @param Instance $instance
     * @param LoggerInterface $log
     * @return bool
     */
    public function isMissingPHPRequirements(Instance $instance, LoggerInterface $log): bool
    {
        return false;
        $missingRequirements = [];
        $access = $instance->getBestAccess();

        $checkPHP = function (string $script) use ($instance, $access) {
            return $access
                ->createCommand($instance->phpexec, ['-r', $script])
                ->run()
                ->getStdoutContent();
        };

        $functionIniSet = $checkPHP("echo function_exists('ini_set');");

        if (! $functionIniSet) {
            $missingRequirements[] = 'function ini_set not found';
        }

        $functionIniGet = $checkPHP("echo function_exists('ini_get');");

        if (! $functionIniGet) {
            $missingRequirements[] = 'Function ini_get not found';
        }

        $phpModules = $this->getPHPModules($instance);

        if (! in_array('pdo_mysql', $phpModules) &&
            ! in_array('mysqli', $phpModules) &&
            ! in_array('mysql', $phpModules)) {
            $missingRequirements[] = 'Module pdo_mysql, mysqli or mysql not loaded';
        }

        $accessMemoryLimit = $functionIniGet ? $checkPHP("echo trim(ini_get('memory_limit'));") : -1;

        if (preg_match('/^(\d+)([GMK])/i', $accessMemoryLimit, $matches)) {
            $shorthandByte = strtoupper($matches[2]);

            if ($shorthandByte == 'G') {
                $memoryLimit = $matches[1] * 1024 * 1024 * 1024;
            } elseif ($shorthandByte == 'M') {
                $memoryLimit = $matches[1] * 1024 * 1024;
            } elseif ($shorthandByte == 'K') {
                $memoryLimit = $matches[1] * 1024;
            } else {
                $memoryLimit = (int) $accessMemoryLimit;
            }
        } else {
            $memoryLimit = (int) $accessMemoryLimit;
        }

        if ($memoryLimit < 128 * 1024 * 1024 && $memoryLimit != -1) {
            $missingRequirements[] = 'memory_limit must be set at least 128M, current value: ' . $accessMemoryLimit;
        }

        $defaultCharset = $functionIniGet ? $checkPHP("echo strtolower(ini_get('default_charset'));") : '';
        if ($defaultCharset !== 'utf-8') {
            $missingRequirements[] = 'default_charset is not UTF-8';
        }

        // Checking PHP modules
        $modules = ['intl','mbstring','ctype','libxml','dom','curl','json','iconv'];

        foreach ($modules as $module) {
            if (! in_array($module, $modules)) {
                $missingRequirements[] = sprintf('Module %s not loaded', $module);
            }
        }

        $mbstringFuncOverload = $functionIniGet ? $checkPHP("echo ini_get('mbstring.func_overload');") : '';

        if ($mbstringFuncOverload && $mbstringFuncOverload != "0") {
            $missingRequirements[] = 'Function mbstring.func_overload not found';
        }

        $evalFunctions = $functionIniGet ? $checkPHP("echo eval('return 42;');") : 0;
        $eval = (int) $evalFunctions;
        if ($eval !== 42) {
            $missingRequirements[] = 'Function eval not found';
        }

        if (!empty($missingRequirements)) {
            $log->error('Missing PHP requirements:' . PHP_EOL . implode(PHP_EOL, $missingRequirements));
            return true;
        }

        return false;
    }

    protected function getPHPModules(Instance $instance): array
    {
        $phpModules = $instance->getBestAccess()
            ->createCommand($instance->phpexec, ['-m'])
            ->run()
            ->getStdoutContent();

        $phpModules = $phpModules ? explode(PHP_EOL, $phpModules) : [];

        if (substr(PHP_OS, 0, 3) == 'WIN' && count($phpModules) == 1) {
            $phpModules = explode("\n", $phpModules[0]);
        }
        return $phpModules;
    }
}
