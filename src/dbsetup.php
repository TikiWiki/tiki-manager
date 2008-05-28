<?php

function perform_database_setup( Instance $instance )
{
	echo "Perform database setup...\n";

	echo "Note: creating databases and users requires root privileges on MySQL.\n";
	$type = readline( "Should a new database and user be created? [yes] " );

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

	$instance->getApplication()->setupDatabase( $db );
}

?>
