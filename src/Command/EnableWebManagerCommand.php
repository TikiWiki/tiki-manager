<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TikiManager\Config\App;

class EnableWebManagerCommand extends TikiManagerCommand
{
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
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Tiki Manager web administration files are located in the Tiki Manager directory. In order to
make the interface available externally, the files will be copied to a web
accessible location.

Permissions on the data folder will be changed to allow the web server to
access the files.

For example, if your web root is /var/www/virtual/webtikimanager.example.com
* Files will be copied to /var/www/virtual/webtikimanager.example.com/html
* Tiki Manager web administration will be accessible from:
    http://webtikimanager.example.com
* You must have write access in /var/www/virtual

Simple authentication will be used. However, it is possible to restrict
access to the administration panel to local users (safer).');

        $this->io->newLine();

        if (!$install = $input->getOption('install')) {
            $install = $this->io->confirm('This will enable the Tiki Manager administration web panel. Continue with this action?', false);

            if (!$install) {
                exit(1);
            }

            $input->setOption('install', $install);
        }

        $path = $this->io->ask(
            'WWW Tiki Manager directory (ex: /var/www/virtual/webtikimanager.example.com/html)',
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
        if (function_exists('posix_getuid')) {
            if (posix_getuid() != 0) {
                throw new \RuntimeException('You need to run this script as root to write to configuration files.');
            }
        } else {
            throw new \RuntimeException('PHP POSIX functions are not installed, install them and try again.');
        }

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

        $user = $input->getOption('www-user') ?? getenv('WWW_USER');
        $group = $input->getOption('www-group') ?? getenv('WWW_GROUP');

        if ($user) {
            if (function_exists('posix_getlogin')) {
                $currentUser = posix_getlogin();
                if ($currentUser != 'root' && $currentUser != $user) {
                    throw new \RuntimeException('You need to run this script as root or as ' . $user . ' to be able to write the files using the www-user provided.');
                }
            }
        }

        if (!is_writable($webPath)) {
            throw new \RuntimeException('You do not have permissions to write in the Web path provided.');
        }

        $cmd = 'cp -a www/. ' . $webPath;
        exec($cmd);

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

        $db = $_ENV['DB_FILE'];
        $data = $_ENV['TRIM_DATA'];
        $backup = $_ENV['BACKUP_FOLDER'];
        $archive = $_ENV['ARCHIVE_FOLDER'];
        $logs = $_ENV['TRIM_LOGS'];
        $cache = $_ENV['CACHE_FOLDER'];
        $composer = $_ENV['COMPOSER_PATH'] == 'composer' ? $_ENV['COMPOSER_PATH'] : 'php ' . $_ENV['COMPOSER_PATH'];

        `chmod 0666 $db`;
        `chmod 0700 $data`;

        `(rm -rf $webPath/vendor && $composer install -d $webPath)`;

        if ($user) {
            $userGroup = $user;
            if ($group) {
                $userGroup = ":" . $group;
            }
            `chown $userGroup $data`;
            `chown $userGroup $backup`;
            `chown $userGroup $archive`;
            `chown $userGroup $logs`;
            `chown $userGroup $cache`;
            `(chown -R $userGroup $webPath/vendor)`;
        }

        $this->io->success('WWW Tiki Manager is now enabled.');
    }
}
