<?php

namespace TikiManager\Command;

use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use TikiManager\Config\Environment;
use TikiManager\Manager\WebInterface\Config;

class EnableWebManagerCommand extends TikiManagerCommand
{
    /**
     * @var \TikiManager\Manager\WebInterface\GenericConfig
     */
    private $conf;

    protected function configure()
    {
        $this
            ->setName('webmanager:enable')
            ->setDescription('Activate Tiki Manager web administration')
            ->setHelp('This command allows you to enable a web interface for Tiki Manager')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_REQUIRED,
                'The folder to install tiki-manager'
            )
            ->addOption(
                'username',
                null,
                InputOption::VALUE_REQUIRED,
                'The username to login'
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'The username\'s password'
            )
            ->addOption(
                'restrict',
                null,
                InputOption::VALUE_NONE,
                'Restrict WebManager access to localhost'
            )
            ->addOption(
                'www-user',
                null,
                InputOption::VALUE_REQUIRED,
                'The apache user (set this if other than apache)'
            )
            ->addOption(
                'www-group',
                null,
                InputOption::VALUE_REQUIRED,
                'The apache group (set this if other than apache)'
            )
            ->addOption(
                'install',
                null,
                InputOption::VALUE_NONE,
                'Proceed tiki-manager installation'
            );
        $this->conf = Config::detect();
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->conf->showMessage($this->io);
        $this->io->newLine();

        if (!$install = $input->getOption('install')) {
            $install = $this->io->confirm('This will enable the Tiki Manager administration web panel. Continue with this action?', false);

            if (!$install) {
                exit(1);
            }

            $input->setOption('install', $install);
        }

        $path = $this->io->ask(
            'WWW Tiki Manager directory (ex: '.$this->conf->getExampleDataDirectory().')',
            getenv('WWW_PATH') ?: null,
            function ($value) {
                if (empty(trim($value))) {
                    throw new \RuntimeException('Tiki Manager directory cannot be empty');
                }
                return $value;
            }
        );

        $input->setOption('path', $path);

        if (file_exists($path . '/config.php')) {
            return;
        }

        if (!$input->getOption('www-user') && !$this->conf->getUserWebRoot($path)) {
            $user = $this->io->ask('WWW Tiki Manager directory user');
            $input->setOption('www-user', $user);
        }

        if (!$input->getOption('www-group') && !$this->conf->getUserWebRoot($path)) {
            $group= $this->io->ask('WWW Tiki Manager directory group');
            $input->setOption('www-group', $group);
        }

        if (!$input->getOption('username')) {
            $username = $this->io->ask('Desired username');
            $input->setOption('username', $username);
        }

        if (!$input->getOption('password')) {
            $password = $this->io->askHidden('Desired password', function ($value) {
                if (empty(trim($value))) {
                    throw new \Exception('The password cannot be empty');
                }
                return $value;
            });
            $input->setOption('password', $password);
        }

        if (!$input->getOption('restrict')) {
            $restrict = $this->io->confirm('Restrict use to localhost', false);
            $input->setOption('restrict', $restrict);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('install')) {
            throw new \RuntimeException('Missing confirmation to proceed installation');
        }

        $webPath = $input->getOption('path') ?? $_ENV['WWW_PATH'];

        if (empty(trim($webPath))) {
            throw new \RuntimeException('Tiki Manager directory cannot be empty');
        }

        if (!is_dir($webPath)) {
            throw new \RuntimeException('Tiki Manager Web path does not exist.');
        }

        if (!is_writable($webPath)) {
            throw new \RuntimeException('You do not have permissions to write in the Web path provided.');
        }

        $user = $input->getOption('www-user') ?? $this->conf->getUserWebRoot($webPath);
        $group = $input->getOption('www-group') ?? $this->conf->getGroupWebRoot($webPath);

        if (!$user) {
            throw new \RuntimeException('Tiki Manager Web path user not defined.');
        }

        if (!$group) {
            throw new \RuntimeException('Tiki Manager Web path group not defined.');
        }

        if (!is_writable($webPath)) {
            $error = sprintf(
                'You need to run this script as root or as %s to be able to write the files into %s',
                $user,
                $webPath
            );
            throw new \RuntimeException($error);
        }

        $fs = new Filesystem();
        $fs->mirror("{$_ENV['TRIM_ROOT']}/www/", $webPath);

        $owner = fileowner($webPath . '/index.php');

        if (!file_exists($webPath . '/config.php')) {
            $username = $input->getOption('username');

            if (empty($username)) {
                throw new \RuntimeException('Username value is missing');
            }

            $password = $input->getOption('password');
            if (empty($password)) {
                throw new \RuntimeException('Password value is missing');
            }

            $restrict = (int) $input->getOption('restrict');

            $tikiManagerPath = realpath(dirname(__FILE__) . '/../..');

            $user = addslashes($username);
            $pass = addslashes($password);

            file_put_contents($webPath . '/config.php', <<<CONFIG
<?php
define('USERNAME', '$user');
define('PASSWORD', '$pass');
define('RESTRICT', $restrict);
define('TIMEOUT', 0);
define('TRIMPATH', '$tikiManagerPath');
define('THEME', 'default');
define('TITLE', 'Tiki Manager Web Administration');
CONFIG
            );
        }

        $db = Environment::get('DB_FILE');
        $data = Environment::get('TRIM_DATA');
        $backup = Environment::get('BACKUP_FOLDER');
        $archive = Environment::get('ARCHIVE_FOLDER');
        $logs = Environment::get('TRIM_LOGS');
        $cache = Environment::get('CACHE_FOLDER');

        try {
            $fs->chmod($db, 0666);
            $fs->chmod($data, 0770);

            $folders = [
                $data,
                $backup,
                $archive,
                $logs,
                $cache,
            ];

            foreach ($folders as $folder) {
                if ($user) {
                    $fs->chown($folder, $user, true);
                }
                if ($group) {
                    $fs->chgrp($folder, $group, true);
                }
            }

            $fs->remove($webPath . '/vendor');

            $composer = Environment::get('COMPOSER_PATH') == 'composer' ?
                [Environment::get('COMPOSER_PATH')] :
                [PHP_BINARY,  Environment::get('COMPOSER_PATH')];
            $command = array_merge(
                $composer,
                [
                    'install',
                    '--no-interaction',
                    '--no-dev',
                    '--prefer-dist',
                    '--no-progress'
                ]
            );

            $process = new Process($command, $webPath);
            $process->setTimeout('300'); // 5min
            $process->run(function ($type, $buffer) {
                $this->io->write($buffer);
            });

            if (!$process->isSuccessful()) {
                throw new Exception('Failed to install composer dependencies.' . PHP_EOL . $process->getErrorOutput());
            }

            $fs->chown($webPath . '/vendor', $owner, true);

            $this->io->success('WWW Tiki Manager is now enabled.');
        } catch (Exception $e) {
            $this->io->error($e->getMessage());
            return 1;
        }
    }
}
