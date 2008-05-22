<?php

include dirname(__FILE__) . "/../src/env_setup.php";

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
if( 'confirm' != readline( "Type 'confirm' to continue: " ) )
	exit;

$d_folder = "/var/www/webtrim";
$folder = readline( "TRIM location : [$d_folder] " );
if( empty( $folder ) )
	$folder = $d_folder;

$user = $pass = '';
while( empty( $user ) )
	$user = readline( "Desired username : " );
while( empty( $pass ) )
	$pass = readline( "Desired password : " );

$d_restrict = 'yes';
$restrict = readline( "Restrict use to localhost : [$d_restrict] " );
if( empty( $restrict ) )
	$restrict = $d_restrict;

$d_sudo = 'no';
$sudo = readline( "Use sudo for permission changing commands : [$d_sudo] " );
if( empty( $sudo ) )
	$sudo = $d_sudo;

$prefix = (strtolower($sudo{0}) == 'y') ? 'sudo' : '';

$user = addslashes( $user );
$pass = addslashes( $pass );
$restrict = (strtolower($restrict{0}) == 'n') ? 'false' : 'true';

file_put_contents( dirname(__FILE__) . '/../www/config.php', <<<CONFIG
<?php
define( 'USERNAME', '$user' );
define( 'PASSWORD', '$pass' );
define( 'RESTRICT', $restrict );
CONFIG
);
$web = realpath( dirname(__FILE__) . '/../www' );

$db = DB_FILE;
$data = dirname( DB_FILE );
`$prefix ln -sf $web $folder`;
`$prefix chmod 0666 $db`;
`$prefix chmod 0777 $data`;

?>
