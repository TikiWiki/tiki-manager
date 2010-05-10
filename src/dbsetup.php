<?php

function perform_instance_installation( Instance $instance )
{
	if( ! $app = $instance->findApplication() )
	{
		$apps = Application::getApplications( $instance );
		echo "No applications were found on remote host.\n";
		echo "Which one do you want to install? (none to skip)\n";
		foreach( $apps as $key => $app )
			echo "[$key] {$app->getName()}\n";

		$selection = readline( ">>> " );
		$selection = getEntries( $apps, $selection );
		if( empty( $selection ) )
			die( "No instance to install.\n" );

		$app = reset( $selection );

		$versions = $app->getVersions();
		echo "Which version do you want to install? (none to skip)\n";
		foreach( $versions as $key => $version )
			echo "[$key] {$version->type} : {$version->branch}\n";

		$input = readline( ">>> " );
		$selection = getEntries( $versions, $input );
		if( empty( $selection ) && empty( $input ) )
			die( "No version to install.\n" );
		elseif( empty( $selection ) )
			$version = Version::buildFake( 'svn', $input );
		else
			$version = reset( $selection );

		info( "Installing application." );
		echo color("If for any reason the installation fails (ex: wrong setup.sh parameters for tikiwiki), you can use `make access` to complete the installation manually.\n", 'yellow');
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
		$type = readline( "Should a new database and user be created? [yes] " );
	} else {
		$type = 'no';
	}

	if( strtolower( $type{0} ) == 'n' )
	{
		$d_host = 'localhost';
		$d_user = 'root';
		$d_pass = '';
		$d_dbname = '';

		$host = readline( "Database host : [$d_host] " );
		if( empty( $host ) ) $host = $d_host;
		$user = readline( "Database user : [$d_user] " );
		if( empty( $user ) ) $user = $d_user;
		$pass = readline( "Database password : [$d_pass] " );
		if( empty( $pass ) ) $pass = $d_pass;
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
		$d_host = 'localhost';
		$d_user = 'root';
		$d_pass = '';

		$host = readline( "Database host : [$d_host] " );
		if( empty( $host ) ) $host = $d_host;
		$user = readline( "Database user : [$d_user] " );
		if( empty( $user ) ) $user = $d_user;
		$pass = readline( "Database password : [$d_pass] " );
		if( empty( $pass ) ) $pass = $d_pass;

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
