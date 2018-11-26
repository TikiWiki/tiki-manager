<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../src/env_setup.php';

if (function_exists('posix_getuid')) {
    if (posix_getuid() != 0) {
        die(error('You need to run this script as root to write to configuration files.'));
    }
} else {
    die(error('PHP POSIX functions are not installed, install them and try again.'));
}

echo <<<INFO
TRIM web administration files are located in the TRIM directory. In order to
make the interface available externally, the files will be copied to a web
accessible location.

Permissions on the data folder will be changed to allow the web server to
access the files.

For example, if your web root is /var/www/virtual/webtrim.example.com
* Files will be copied to /var/www/virtual/webtrim.example.com/html
* TRIM web administration will be accessible from:
    http://webtrim.example.com
* You must have write access in /var/www/virtual

Simple authentification will be used. However, it is possible to restrict
access to the administration panel to local users (safer).


INFO;

echo "This will enable the TRIM administration web panel.\n";
if ('confirm' != promptUser('Type \'confirm\' to continue', '')) {
    exit(1);
}

$webTrimDirectory = promptUser('WWW Trim directory (ex: /var/www/virtual/webtrim.example.com/html)');
$cmd = 'cp -a www/. ' . $webTrimDirectory . '; cp -a composer.phar ' . $webTrimDirectory;
exec($cmd);

$owner = fileowner($webTrimDirectory . '/index.php');

if (! file_exists($webTrimDirectory . '/config.php')) {
    $pass = '';
    $user = promptUser('Desired username');

    while (empty($pass)) {
        print 'Desired password : ';
        $pass = getPassword(true);
        print "\n";
    }

    $restrict = promptUser('Restrict use to localhost', 'no');
    $restrict = (strtolower($restrict{0}) == 'n') ? 'false' : 'true';
    $trimpath = realpath(dirname(__FILE__) . '/..');

    $user = addslashes($user);
    $pass = addslashes($pass);

    file_put_contents($webTrimDirectory . '/config.php', <<<CONFIG
<?php
define('USERNAME', '$user');
define('PASSWORD', '$pass');
define('RESTRICT', $restrict);
define('TIMEOUT', 0);
define('TRIMPATH', '$trimpath');
define('THEME', 'default');
define('TITLE', 'TRIM Web Administration');
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

echo "WWW Trim is now enabled.\n";
echo "Enjoy!\n";
