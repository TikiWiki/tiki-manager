<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

$cur = trim( `pwd` );

if( array_key_exists( 'REQUEST_METHOD', $_SERVER ) )
	chdir( $_SERVER['QUERY_STRING'] );
elseif( count( $_SERVER['argv'] ) > 1 )
	chdir( $_SERVER['argv'][1] );
elseif( isset( $folder ) )
	chdir( $folder );

function md5_file_recurse( $location, &$fulllist )
{
	$files = scandir( $location );
	foreach( $files as $child )
	{
		if( in_array( $child, array( '.', '..', 'CVS', '.svn' ) ) )
			continue;

		$full = "$location/$child";
		$full = realpath( $full );

		if( array_key_exists( $full, $fulllist ) )
			continue;

		if( is_dir( $full ) )
			md5_file_recurse( $full, $fulllist );

		if( in_array( substr( $child, -4 ), array( '.php', '.tpl' ) ) )
			$fulllist[$full] = md5_file( $full );
	}
}

$list = array();
md5_file_recurse( '.', $list );

$root = trim( `pwd` );
foreach( $list as $file => $hash )
{
	$file = str_replace( $root, '.', $file );
	echo "$hash:$file\n";
}

chdir( $cur );

?>
