<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Access\Access;
use TikiManager\Access\ShellPrompt;
use TikiManager\Application\Application;
use TikiManager\Application\Discovery;
use TikiManager\Application\Instance;
use TikiManager\Command\Helper\CommandHelper;
use TikiManager\Ext\Password;
use TikiManager\Libs\Database\Database;
use TikiManager\Libs\Helpers\ApplicationHelper;

class CreateInstanceCommand extends Command
{
    private static $nonInteractive;

    protected function configure()
    {
        $this
            ->setName('instance:create')
            ->setDescription('Creates a new instance')
            ->setHelp('This command allows you to create a new instance')
            ->addOption(
                'blank',
                null,
                InputOption::VALUE_NONE,
                'Blank Instance'
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Instance connection type'
            )
            ->addOption(
                'host',
                'rh',
                InputOption::VALUE_REQUIRED,
                'Remote host name'
            )
            ->addOption(
                'port',
                'rp',
                InputOption::VALUE_REQUIRED,
                'Remote port number'
            )
            ->addOption(
                'user',
                'ru',
                InputOption::VALUE_REQUIRED,
                'Remote User'
            )
            ->addOption(
                'pass',
                'rrp',
                InputOption::VALUE_REQUIRED,
                'Remote password'
            )
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_REQUIRED,
                'Instance web url'
            )
            ->addOption(
                'name',
                'na',
                InputOption::VALUE_REQUIRED,
                'Instance name'
            )
            ->addOption(
                'email',
                'e',
                InputOption::VALUE_REQUIRED,
                'Instance contact email'
            )
            ->addOption(
                'webroot',
                'wr',
                InputOption::VALUE_REQUIRED,
                'Instance web root'
            )
            ->addOption(
                'tempdir',
                'td',
                InputOption::VALUE_REQUIRED,
                'Instance temporary directory'
            )
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_REQUIRED,
                'Instance branch'
            )
            ->addOption(
                'backup-user',
                'bu',
                InputOption::VALUE_REQUIRED,
                'Instance backup user'
            )
            ->addOption(
                'backup-group',
                'bg',
                InputOption::VALUE_REQUIRED,
                'Instance backup group'
            )
            ->addOption(
                'backup-permission',
                'bp',
                InputOption::VALUE_REQUIRED,
                'Instance backup permission'
            )
            ->addOption(
                'db-host',
                'dh',
                InputOption::VALUE_REQUIRED,
                'Instance database host'
            )
            ->addOption(
                'db-user',
                'du',
                InputOption::VALUE_REQUIRED,
                'Instance database user'
            )
            ->addOption(
                'db-pass',
                'dp',
                InputOption::VALUE_REQUIRED,
                'Instance database password'
            )
            ->addOption(
                'db-prefix',
                'dpx',
                InputOption::VALUE_REQUIRED,
                'Instance database prefix'
            );

        self::$nonInteractive = false;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \TikiManager\Application\Exception\ConfigException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $nonInteractive = $this->isNonInteractive($input, $output);

        if (!empty($nonInteractive)) {
            $instance = $nonInteractive['instance'];
            $discovery = $nonInteractive['discovery'];
            $access = $nonInteractive['access'];
            self::$nonInteractive = true;
        } elseif ($nonInteractive === false) {
            return 0;
        }

        $errors = [];

        if (!self::$nonInteractive) {
            $io->title('Create a new instance');

            $blank = $input->getOption('blank') ? true : false;

            $output->writeln('<comment>Answer the following to add a new Tiki Manager instance.</comment>');

            $instance = new Instance();

            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion('Connection type:', $this->getInstanceType());
            $question->setErrorMessage('Connection type %s is invalid.');
            $instance->type = $type = $helper->ask($input, $output, $question);

            $access = Access::getClassFor($instance->type);
            $access = new $access($instance);
            $discovery = new Discovery($instance, $access);

            if ($type != 'local') {
                $question = CommandHelper::getQuestion('Host name');
                $access->host = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('Port number', ($type == 'ssh') ? 22 : 21);
                $access->port = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('User');
                $access->user = $helper->ask($input, $output, $question);

                $question = CommandHelper::getQuestion('Password');
                $question->setHidden(true);
                $question->setHiddenFallback(false);

                while ($type == 'ftp' && empty($access->password)) {
                    $access->password = $helper->ask($input, $output, $question);
                }
            } else {
                $access->host = 'localhost';
                $access->user = $discovery->detectUser();
            }

            $question = CommandHelper::getQuestion('Web URL', $discovery->detectWeburl());
            $instance->weburl = $helper->ask($input, $output, $question);

            $question = CommandHelper::getQuestion('Instance name', $discovery->detectName());
            $instance->name = $helper->ask($input, $output, $question);

            $question = CommandHelper::getQuestion('Contact email');
            $question->setValidator(function ($value) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Please insert a valid email address.');
                }
                return $value;
            });
            $instance->contact = $helper->ask($input, $output, $question);

            if (!$access->firstConnect()) {
                error('Failed to setup access');
            }

            $instance->save();
            $access->save();
            $output->writeln('<info>Instance information saved.</info>');

            $phpVersion = $discovery->detectPHPVersion();
            if (preg_match('/(\d+)(\d{2})(\d{2})$/', $phpVersion, $matches)) {
                $phpVersion = sprintf("%d.%d.%d", $matches[1], $matches[2], $matches[3]);
            }

            $output->writeln('<info>Running on ' . $discovery->detectDistro() . '</info>');
            $output->writeln('<info>PHP Version: ' . $phpVersion . '</info>');
            $output->writeln('<info>PHP exec: ' . $discovery->detectPHP() . '</info>');

            $folders = [
                'webroot' => [
                    'question' => 'Webroot directory',
                    'default' => $discovery->detectWebroot(),
                ],
                'tempdir' => [
                    'question' => 'Working directory',
                    'default' => TRIM_TEMP,
                ]
            ];

            foreach ($folders as $key => $folder) {
                $question = CommandHelper::getQuestion($folder['question'], $folder['default']);
                $path = $helper->ask($input, $output, $question);

                if ($access instanceof ShellPrompt) {
                    $phpPath = $access->getInterpreterPath();

                    $script = sprintf("echo is_dir('%s');", $path);
                    $command = $access->createCommand($phpPath, ["-r {$script}"]);
                    $command->run();

                    if (empty($command->getStdoutContent())) {
                        $output->writeln('Directory [' . $path . '] does not exist.');

                        $helper = $this->getHelper('question');
                        $question = new ConfirmationQuestion('Create directory? [y]: ', true);

                        if (!$helper->ask($input, $output, $question)) {
                            $output->writeln('<error>Directory [' . $path . '] not created.</error>');
                            $errors[$key] = $path;
                            continue;
                        }

                        $script = sprintf("echo mkdir('%s', 0777, true);", $path);
                        $command = $access->createCommand($phpPath, ["-r {$script}"]);
                        $command->run();

                        if (empty($command->getStdoutContent())) {
                            $output->writeln('<error>Unable to create directory [' . $path . ']</error>');
                            $errors[] = $path;
                            continue;
                        }
                    }
                } else {
                    $output->writeln('<error>Shell access is required to create ' . strtolower($folder['question']) . '. You will need to create it manually.</error>');
                    $errors[] = $path;
                    continue;
                }

                $instance->$key = $path;
            }

            $phpVersion = $discovery->detectPHPVersion();
            CommandHelper::displayPhpVersion($phpVersion, $io);

            list($backup_user, $backup_group, $backup_perm) = $discovery->detectBackupPerm();

            $question = CommandHelper::getQuestion('Backup owner', $backup_user);
            $backup_user = $helper->ask($input, $output, $question);

            $question = CommandHelper::getQuestion('Backup group', $backup_group);
            $backup_group = $helper->ask($input, $output, $question);

            $question = CommandHelper::getQuestion('Backup file permissions', decoct($backup_perm));
            $backup_perm = $helper->ask($input, $output, $question);

            $instance->backup_user = trim($backup_user);
            $instance->backup_group = trim($backup_group);
            $instance->backup_perm = octdec($backup_perm);
        }

        $instance->vcs_type = $discovery->detectVcsType();
        $instance->phpexec = $discovery->detectPHP();
        $instance->phpversion = $discovery->detectPHPVersion();

        $instance->save();
        $access->save();

        $output->writeln('<info>Instance information saved.</info>');

        if (array_key_exists('webroot', $errors) && $instance->type !== 'ftp') {
            $output->writeln('<error>Webroot directory is missing in filesystem. You need to create it manually.</error>');
            $output->writeln('<fg=blue>Instance configured as blank (empty).</>');
            return 0;
        }

        $countInstances = Instance::countNumInstances($instance);
        $isInstalled = false;

        foreach (Application::getApplications($instance) as $app) {
            if ($app->isInstalled()) {
                $isInstalled = true;
            }
        }

        if ($isInstalled) {
            if ($countInstances == 1) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion('An application was detected in [' . $instance->webroot . '], do you want add it to the list? [y]: ', true);

                if (!$helper->ask($input, $output, $question)) {
                    $instance->delete();
                    return 1;
                }
                $result = $app->registerCurrentInstallation();
                $resultInstance = $result->getInstance();

                if ($instance->id === $resultInstance->id) {
                    $io->success('Please test your site at ' . $instance->weburl);
                    return 0;
                }
            } else {
                $instance->delete();
                $io->error('Unable to install. An application was detected in this instance.');
                return 1;
            }
        }

        if ((!self::$nonInteractive && $blank) || (isset($instance->selection) && $instance->selection == 'blank : none')) {
            $output->writeln('<fg=blue>This is a blank (empty) instance. This is useful to restore a backup later.</>');
            return 0;
        }

        $result = CommandHelper::performInstall($instance, $input, $output, self::$nonInteractive);

        if ($result === false) {
            return 1;
        }

        return 0;
    }

    /**
     * Check non interactive instance creation mode
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array|bool|int
     * @throws \Exception
     */
    protected function isNonInteractive(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $fs = new Filesystem();

        $listInstanceTypes = $this->getInstanceType();
        $listInstanceTypeKeys = array_keys($listInstanceTypes);

        $type = $input->getOption('type');
        $weburl = $input->getOption('url');
        $name = $input->getOption('name');
        $contact = $input->getOption('email');
        $webroot = $input->getOption('webroot');
        $tempdir = $input->getOption('tempdir');
        $backupUser = $input->getOption('backup-user');
        $backupGroup = $input->getOption('backup-group');
        $backupPerm = $input->getOption('backup-permission');
        $version = $input->getOption('branch');

        if (!empty($type)
            && !empty($weburl)
            && !empty($name)
            && !empty($contact)
            && !empty($webroot)
            && !empty($tempdir)
            && !empty($version)
            && !empty($backupUser)
            && !empty($backupGroup)
            && !empty($backupPerm)
        ) {
            if (!in_array($type, $listInstanceTypes) || !in_array($type, $listInstanceTypeKeys)) {
                $io->error('Instance type invalid.');
                return false;
            }

            if (filter_var($weburl, FILTER_VALIDATE_URL) === false) {
                $io->error('Instance web url invalid.');
                return false;
            }

            if (filter_var($contact, FILTER_VALIDATE_EMAIL) === false) {
                $io->error('Please insert a valid email address.');
                return false;
            }

            if ($fs->exists($webroot)) {
                $isInstalled = $fs->exists($webroot . DIRECTORY_SEPARATOR . 'tiki-setup.php');
                if ($isInstalled) {
                    $io->error('Unable to install. An application was detected in this instance.');
                    return false;
                }
            }

            if (!is_numeric($backupPerm)) {
                $io->error('Backup file permissions is not numeric.');
                return false;
            }

            if ($type != 'local') {
                $rhost = $input->getOption('host');
                $rport = $input->getOption('port');
                $ruser = $input->getOption('user');
                $rpass = $input->getOption('pass');

                if (empty($rhost) || !is_numeric($rport) || empty($ruser) || empty($rpass)) {
                    $io->error('Remote server credentials are missing.');
                    return false;
                }
            }

            $instance = new Instance();

            $type = is_numeric($type) ? $listInstanceTypes[$type] : $type;
            $instance->type = $type;
            $instance->weburl = $weburl;
            $instance->name = $name;
            $instance->contact = $contact;
            $instance->webroot = $webroot;
            $instance->tempdir = $tempdir;
            $instance->backup_user = $backupUser;
            $instance->backup_group = $backupGroup;
            $instance->backup_perm = $backupPerm;

            $access = Access::getClassFor($type);
            $access = new $access($instance);
            $discovery = new Discovery($instance, $access);

            if ($type != 'local') {
                $access->host = $rhost;
                $access->port = $rport;
                $access->user = $ruser;
                $access->password = $rpass;
            }

            if (!$access->firstConnect()) {
                error('Failed to setup access');
                exit(1);
            }

            $instance->save();
            $access->save();

            if (!$fs->exists($webroot)) {
                $phpPath = $access->getInterpreterPath();
                $script = sprintf("echo mkdir('%s', 0777, true);", $webroot);
                $command = $access->createCommand($phpPath, ["-r {$script}"]);
                $command->run();

                if (empty($command->getStdoutContent())) {
                    $io->error('Unable to create directory [' . $webroot . ']');
                    return false;
                }
            }

            $instance->phpexec = $discovery->detectPHP();
            $instance->phpversion = $discovery->detectPHPVersion();

            $apps = Application::getApplications($instance);
            $selection = getEntries($apps, 0);
            $app = reset($selection);
            $versions = $app->getCompatibleVersions(false); // exclude blank

            $branch = '';
            if ($version != 'blank') {
                foreach ($versions as $versionInfo) {
                    if ($version == $versionInfo->branch) {
                        $branch = $versionInfo->type . ' : ' . $versionInfo->branch;
                        break;
                    }
                }
            } else {
                $branch = 'blank : none';
            }

            if (empty($branch)) {
                $io->error('Version value "' . $version . '" is invalid.');
                return false;
            }

            $instance->selection = $branch;

            if ($instance->selection != 'blank : none') {
                $dbHost = $input->getOption('db-host');
                $dbUser = $input->getOption('db-user');
                $dbPass = $input->getOption('db-pass');
                $dbprefix = !empty($input->getOption('db-prefix')) ? $input->getOption('db-prefix') : 'tiki';

                if (empty($dbHost)
                    || empty($dbUser)
                    || empty($dbPass)
                    || empty($dbprefix)
                ) {
                    $io->error('Database credentials are missing.');
                    return false;
                }

                if (strlen($dbprefix) > 27) {
                    $io->error('Prefix is a string with maximum of 27 chars');
                    return false;
                }

                $credentials['host'] = $dbHost;
                $credentials['user'] = $dbprefix . '_user';
                $credentials['password'] = Password::create(12, 'unpronounceable');
                $dbname = $dbprefix . '_db';
                $credentials['dbname'] = $dbname;
                $credentials['create'] = true;

                $dbRoot = new Database($instance);
                $dbRoot->host = $dbHost;
                $dbRoot->user = $dbUser;
                $dbRoot->pass = $dbPass;
                $dbRoot->dbname = $dbname;

                $valid = $dbRoot->testConnection();
                if (!$valid) {
                    $io->error('Can\'t connect to database server!');
                    return false;
                }

                if ($credentials['create'] && $dbUser = $dbRoot->createAccess($credentials['user'], $dbname)) {
                    $instance->database = $dbUser;
                } else {
                    $instance->database = $dbRoot;
                }
            }

            return [
                'instance' => $instance,
                'discovery' => $discovery,
                'access' => $access
            ];
        }

        return 0;
    }

    /**
     * Get instance types
     *
     * @return array
     */
    protected function getInstanceType()
    {
        $instanceTypes = ApplicationHelper::isWindows() ? 'local' : Instance::TYPES;
        $listInstanceTypes = explode(',', $instanceTypes);
        return $listInstanceTypes;
    }
}
