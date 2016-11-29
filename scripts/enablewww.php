<?php
if (function_exists('posix_getuid')){
	if (posix_getuid() == 0){
		echo "Good! This is root!\n";
	} 
	else {
	        echo "This is non-root";
		echo "You need to execute this script as root, it will write some configuration files";
		exit(1);
	}
}
else {
	echo "PHP POSIX functions are not installed, install them and try again\n";
	exit(1);
}

include_once dirname(__FILE__) . "/../src/env_setup.php";

echo <<<INFO
TRIM web administration files are located in the TRIM directory. In order to
make the interface available externally, a symbolic link from a web accessible
location will be used.

Permissions on the data folder will be changed to allow the web server to
access the files.

For example, if your web root is /var/www
* A link will be created from /var/www/webtrim ===> $root/www
* TRIM web administration will be accessible from:
	http://localhost/webtrim
* You must have write access in /var/www

Simple authentification will be used. However, it is possible to restrict
access to the administration panel to local users (safer).


INFO;
echo "This will enable the TRIM administration web panel.\n";
if( 'confirm' != promptUser( "Type 'confirm' to continue: ", '' ) )
	exit;

$ret_var = -1;
$out = '/etc/httpd/';
$cmd = 'dirname $(find /etc/httpd -name httpd.conf )';
exec ($cmd, $out, $ret_var);

$d_httpd = promptUser('Apache httpd.conf directory', $out[0]);
$base = dirname($d_httpd);
$precmd = 'dirname $(find '.$base.' -name httpd.conf -exec grep ^Include {} \; | cut -d'."' '".' -f2)|sort|head -n1';
$cmd = $precmd;
exec ($cmd, $out, $ret_var);

$d_httpd_optional = promptUser('Apache httpd.conf IncludeOptional directory', $base.'/'.$out[1]);

//$folder = promptUser('TRIM location', '/var/www/webtrim');

$user = $pass = '';
$user = promptUser('Desired username');

while( empty( $pass ) ) {
	print "Desired password : ";
	$pass = getPassword(true); print "\n";
}

$restrict = promptUser('Restrict use to localhost', 'no');
$restrict = (strtolower($restrict{0}) == 'n') ? 'false' : 'true';

$sudo = promptUser('Use sudo for permission changing commands', 'no');
$prefix = (strtolower($sudo{0}) == 'y') ? 'sudo' : '';

$user = addslashes( $user );
$pass = addslashes( $pass );

file_put_contents( dirname(__FILE__) . '/../www/config.php', <<<CONFIG
<?php
define( 'USERNAME', '$user' );
define( 'PASSWORD', '$pass' );
define( 'RESTRICT', $restrict );
CONFIG
);

$web = realpath( dirname(__FILE__) . '/../www' );

file_put_contents ( $d_httpd_optional.'/webtrim.conf', <<<CONFIG
Alias /webtrim $web

<Directory $web/>

AllowOverride All
<IfModule mod_authz_core.c>
        # Apache 2.4
       Require all granted
	LogLevel alert rewrite:trace6
</IfModule>
<IfModule !mod_authz_core.c>
        # Apache 2.2
        order deny,allow
        allow from all
</IfModule>
</Directory>
CONFIG
);

$db = DB_FILE;
$data = dirname( DB_FILE );
#`$prefix ln -sf $web $folder`;
`$prefix chmod 0666 $db`;
`$prefix chmod 0777 $data`;

echo "Please restart your apache before continuing\n";
echo "WWW Trim is now enabled\n";
echo "Go to your browser and do http://server_ip/webtrim/\n";
echo "Enjoy!\n";
