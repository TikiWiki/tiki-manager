<?php

namespace TikiManager\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use TikiManager\Command\Helper\CommandHelper;

class EnableWebManagerCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('webmanager:enable')
            ->setDescription('Activate Tiki Manager web administration')
            ->setHelp('This command allows you to enable a web interface for Tiki Manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (function_exists('posix_getuid')) {
            if (posix_getuid() != 0) {
                $output->writeln('<error>You need to run this script as root to write to configuration files.</error>');
                exit(-1);
            }
        } else {
            $output->writeln('<error>PHP POSIX functions are not installed, install them and try again.</error>');
            exit(-1);
        }

        $io->newLine();

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

Simple authentification will be used. However, it is possible to restrict
access to the administration panel to local users (safer).');

        $io->newLine();

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('<comment>This will enable the Tiki Manager administration web panel.</comment>
Continue with this action (y,n)? ', false);

        if (! $helper->ask($input, $output, $question)) {
            return;
        }

        $io->newLine();

        $question = CommandHelper::getQuestion('WWW Tiki Manager directory (ex: /var/www/virtual/webtikimanager.example.com/html)');
        $question->setValidator(function ($value) {
            if (empty(trim($value))) {
                throw new \RuntimeException('Tiki Manager directory cannot be empty');
            }
            return $value;
        });
        $webTrimDirectory = $helper->ask($input, $output, $question);
        $cmd = 'cp -a www/. ' . $webTrimDirectory . '; cp -a composer.phar ' . $webTrimDirectory;
        exec($cmd);

        $owner = fileowner($webTrimDirectory . '/index.php');

        if (! file_exists($webTrimDirectory . '/config.php')) {
            $question = CommandHelper::getQuestion('Desired username');
            $username = $helper->ask($input, $output, $question);

            $question = CommandHelper::getQuestion('Desired password');
            $question->setValidator(function ($value) {
                if (empty(trim($value))) {
                    throw new \Exception('The password cannot be empty');
                }
                return $value;
            });
            $question->setHidden(true);
            $password = $helper->ask($input, $output, $question);

            $question = CommandHelper::getQuestion('Restrict use to localhost', 'no');
            $question->setNormalizer(function ($value) {
                return (strtolower($value{0}) == 'n') ? 'false' : 'true';
            });
            $restrict = $helper->ask($input, $output, $question);

            $tiki_manager_path = realpath(dirname(__FILE__) . '/../..');

            $user = addslashes($username);
            $pass = addslashes($password);

            file_put_contents($webTrimDirectory . '/config.php', <<<CONFIG
<?php
define('USERNAME', '$user');
define('PASSWORD', '$pass');
define('RESTRICT', $restrict);
define('TIMEOUT', 0);
define('TRIMPATH', '$tiki_manager_path');
define('THEME', 'default');
define('TITLE', 'Tiki Manager Web Administration');
CONFIG
            );
        }

        $db = DB_FILE;
        $data = TRIM_DATA;
        $backup = BACKUP_FOLDER;
        $archive = ARCHIVE_FOLDER;
        `chmod 0666 $db`;
        `chmod 0700 $data`;
        `chown apache:apache $data`;
        `chown apache:apache $backup`;
        `chown apache:apache $archive`;
        `(cd $webTrimDirectory && rm -rf vendor && php composer.phar install)`;
        `(cd $webTrimDirectory && chown -R $owner vendor)`;

        $output->writeln('<info>WWW Tiki Manager is now enabled.</info>');
        $output->writeln('<info>Enjoy!</info>');
    }
}
