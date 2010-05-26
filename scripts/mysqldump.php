<?php
/*---------------------------------------------------+
| mysqldump.php
+----------------------------------------------------+
| Copyright 2006 Huang Kai
| hkai@atutility.com
| http://atutility.com/
+----------------------------------------------------+
| Released under the terms & conditions of v2 of the
| GNU General Public License. For details refer to
| the included gpl.txt file or visit http://gnu.org
+----------------------------------------------------*/
/*
change log:
2006-10-16 Huang Kai
---------------------------------
initial release

2006-10-18 Huang Kai
---------------------------------
fixed bugs with delimiter
add paramter header to add field name as CSV file header.

2006-11-11 Huang Kia
Tested with IE and fixed the <button> to <input>

2009-06-23 LPH
Removed CSV, UI
Limited query length for large data sets
Abstraction for mysql, mysqli
Use unbuffered query to extract data
Get credentials from local file (for TikiWiki)
*/

// Wrappers {{{

if( function_exists( 'mysqli_query' ) )
{
	function connect( $host, $user, $pass )
	{
		global $mysqli_db_link;
		return $mysqli_db_link = mysqli_connect( $host, $user, $pass );
	}

	function select_db( $db, $link )
	{
		return mysqli_select_db( $link, $db );
	}

	function query( $sql )
	{
		global $mysqli_db_link;
		return mysqli_query( $mysqli_db_link, $sql );
	}

	function unbuffered_query( $sql )
	{
		global $mysqli_db_link;
		mysqli_real_query( $mysqli_db_link, $sql );
		return mysqli_use_result( $mysqli_db_link );
	}

	function fetch_assoc( $result )
	{
		global $mysqli_db_link;
		return mysqli_fetch_assoc( $result );
	}

	function fetch_row( $result )
	{
		global $mysqli_db_link;
		return mysqli_fetch_row( $result );
	}

	function free( $result )
	{
		global $mysqli_db_link;
		return mysqli_free_result( $result );
	}

	function escape( $data )
	{
		global $mysqli_db_link;
		return mysqli_real_escape_string( $mysqli_db_link, $data );
	}

	function num_fields( $result )
	{
		global $mysqli_db_link;
		return mysqli_num_fields( $result );
	}

	function fetch_field( $result, $field )
	{
		global $mysqli_db_link;
		return mysqli_fetch_field_direct( $result, $field );
	}
}
else
{
	function connect( $host, $user, $pass )
	{
		return mysql_connect( $host, $user, $pass );
	}

	function select_db( $db, $link )
	{
		return mysql_select_db( $db, $link );
	}

	function query( $sql )
	{
		return mysql_query( $sql );
	}

	function unbuffered_query( $sql )
	{
		return mysql_unbuffered_query( $sql );
	}

	function fetch_assoc( $result )
	{
		return mysql_fetch_assoc( $result );
	}

	function fetch_row( $result )
	{
		return mysql_fetch_row( $result );
	}

	function free( $result )
	{
		return mysql_free_result( $result );
	}

	function escape( $data )
	{
		return mysql_real_escape_string( $data );
	}

	function num_fields( $result )
	{
		return mysql_num_fields( $result );
	}

	function fetch_field( $result, $field )
	{
		return mysql_fetch_field( $result, $field );
	}
}

// }}}

$mysqldump_version="1.02";

include_once 'db/local.php';

$mysql_host=$host_tiki;
$mysql_database=$dbs_tiki;
$mysql_username=$user_tiki;
$mysql_password=$pass_tiki;

_mysql_test($mysql_host,$mysql_database, $mysql_username, $mysql_password);

//ob_start("ob_gzhandler");
header('Content-type: text/plain');
//header('Content-Disposition: attachment; filename="'.$mysql_host."_".$mysql_database."_".date('YmdHis').'.sql"');
echo "/*mysqldump.php version $mysqldump_version */\n";
_mysqldump($mysql_database);

//header("Content-Length: ".ob_get_length());

//ob_end_flush();

function _mysqldump($mysql_database)
{
	$sql="show tables;";
	$result= query($sql);
	if( $result)
	{
		while( $row= fetch_row($result))
		{
			_mysqldump_table_structure($row[0]);
			
			_mysqldump_table_data($row[0]);
		}
	}
	else
	{
		echo "/* no tables in $mysql_database */\n";
	}
	free($result);
}

function _mysqldump_table_structure($table)
{
	echo "/* Table structure for table `$table` */\n";
	echo "DROP TABLE IF EXISTS `$table`;\n\n";
	
	$sql="show create table `$table`; ";
	$result=query($sql);
	if( $result)
	{
		if($row= fetch_assoc($result))
		{
			echo $row['Create Table'].";\n\n";
		}
	}
	free($result);
}

function _mysqldump_table_data($table)
{
	$sql="select COUNT(*) from `$table`;";
	$result=query($sql);
	$num_rows= fetch_row($result);
	$num_rows = $num_rows[0];
	
	$sql="select * from `$table`;";
	$result=unbuffered_query($sql);
	if( $result)
	{
		$num_fields= num_fields($result);
		
		if( $num_rows > 0)
		{
			echo "/* dumping data for table `$table` */\n";
			
			$field_type=array();
			$i=0;
			while( $i < $num_fields)
			{
				$meta= fetch_field($result, $i);
				array_push($field_type, $meta->type);
				$i++;
			}

			$output_length = 0;
			
			echo "insert into `$table` values\n";
			$index=0;
			while( $row= fetch_row($result))
			{
				echo "(";
				for( $i=0; $i < $num_fields; $i++)
				{
					if( is_null( $row[$i]))
						$out = "null";
					else
					{
						switch( $field_type[$i])
						{
							case 'int':
								$out = $row[$i];
								break;
							case 'string':
							case 'blob' :
							default:
								$out = "'".escape($row[$i])."'";
								
						}
					}
					echo $out;
					$output_length += strlen( $out ) + 1;
					if( $i < $num_fields-1)
						echo ",";
				}
				echo ")";
				
				if( $index < $num_rows-1) {
					if( $output_length > 100000 )
					{
						$output_length = 0;
						echo ";";
						echo "\ninsert into `$table` values";
					}
					else
					{
						echo ",";
					}
				}
				else
					echo ";";
				echo "\n";
				
				$index++;
			}
		}
	}
	free($result);
	echo "\n";
}

function _mysql_test($mysql_host,$mysql_database, $mysql_username, $mysql_password)
{
	$link = connect($mysql_host, $mysql_username, $mysql_password);
	if( $link )
	{
		$db_selected = select_db($mysql_database, $link);
	}
}
