<?php

function perform_instance_installation( Instance $instance )
{
	if( ! $app = $instance->findApplication() )
	{
		$apps = Application::getApplications( $instance );
		echo "No applications were found on remote host.\n";
		echo "Which one do you want to install? (none to skip - blank instance)\n";
		foreach( $apps as $key => $app )
			echo "[$key] {$app->getName()}\n";

		$selection = readline( ">>> " );
		$selection = getEntries( $apps, $selection );
		if( empty( $selection ) )
			die( "No instance to install.\n" );

		$app = reset( $selection );

		$versions = $app->getVersions();
		echo "Which version do you want to install? (none to skip - blank instance)\n";
		foreach( $versions as $key => $version ){
			preg_match('/(\d+\.|trunk)/',$version->branch, $matches);
			if ((($matches[0] >= 13) || ($matches[0] == 'trunk')) && ($instance->phpversion < 50500)){
				// none to do, this match is incompatible
			}
			else
				echo "[$key] {$version->type} : {$version->branch}\n";
		}
				echo "[-1] blank : none\n";

		$input = readline( ">>> " );
		if ($input > -1)
			$selection = getEntries( $versions, $input );

		if (( empty( $selection ) && empty( $input ) ) ||
			($input < 0)
		)
			die( "No version to install.\nThis is a blank instance\n" );
		elseif( empty( $selection ) )
			$version = Version::buildFake( 'svn', $input );
		else
			$version = reset( $selection );

		info( "Installing application." );
		echo color("If for any reason the installation fails (ex: wrong setup.sh parameters for tiki), you can use `make access` to complete the installation manually.\n", 'yellow');
		$app->install( $version );

		if( $app->requiresDatabase() )
			perform_database_setup( $instance );
	}
}

function perform_database_setup( Instance $instance, $remoteBackupFile = null )
{
	echo "Perform database setup...\n";

	$access = $instance->getBestAccess('scripting');

	if( $access instanceof ShellPrompt ) {
		echo "Note: creating databases and users requires root privileges on MySQL.\n";
		$type = strtolower(readline( "Should a new database and user be created (both)? [yes] " ));
	} else {
		$type = 'no';
	}

	$d_host = 'localhost';
	$d_user = 'root';
	$d_pass = '';

	$host = strtolower(readline( "Database host : [$d_host] " ));
	if( empty( $host ) ) $host = $d_host;
	$user = strtolower(readline( "Database user : [$d_user] " ));
	if( empty( $user ) ) $user = $d_user;
	print "Database password : [$d_pass] ";
	$pass = getPassword(true); print "\n";
//	$pass = readline( "Database password : [$d_pass] " );
	if( empty( $pass ) ) $pass = $d_pass;

	print "Testing connectivity and DB data...\n";
	$command = "mysql -u ${user} ".(empty($pass)?"":"-p${pass}")." -h ${host} -e 'SELECT 1' 2> /dev/null > /dev/null ; echo $?";
        $e =  $access->shellExec($command);
	if ($e){
		print "TRIM was unable to use of create your database with the information you provided. Aborting the installation\n";
		exit;
	}

	if( strtolower( $type{0} ) == 'n' )
	{
		$d_dbname = '';

		while( empty( $dbname ) )
			$dbname = readline( "Database name : " );

		$adapter = new Database_Adapter_Dummy();
		$db = new Database( $instance, $adapter );
		$db->host = $host;
		$db->user = $user;
		$db->pass = $pass;
		$db->dbname = $dbname;
	}
	else
	{
		$adapter = new Database_Adapter_Mysql( $host, $user, $pass );
		$db = new Database( $instance, $adapter );
		$db->host = $host;

		$prefix = '';
		while( empty( $prefix ) )
			$prefix = readline( "Prefix to use for username and database : " );

		$db->createAccess( $prefix );
	}

	$types = $db->getUsableExtensions();
	if( count( $types ) == 1 )
		$db->type = reset( $types );
	else
	{
		echo "Which extension should be used? [0]\n";
		foreach( $types as $key => $name )
			echo "[$key] $name\n";

		$selection = readline( ">>> " );
		if( array_key_exists( $selection, $types ) )
			$db->type = $types[$selection];
		else
			$db->type = reset( $types );
	}

	if( $remoteBackupFile )
		$instance->getApplication()->restoreDatabase( $db, $remoteBackupFile );
	else
		$instance->getApplication()->setupDatabase( $db );
}
