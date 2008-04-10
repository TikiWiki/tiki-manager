<?php
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

function handleCheckResult( $instance, $version, $array )
{
	extract( $array ); // $new, $mod, $del

	// New {{{
	$input = 'p';
	$newFlat = array_keys( $new );

	while( $input != 's' && count( $new ) )
	{
		echo "New files were found on remote host :\n";
		foreach( $newFlat as $key => $file )
			echo "\t[$key] $file\n";

		echo "\n\n";

		$input = 'z';
		while( stripos( 'pvdas', $input{0} ) === false )
		{
			echo "\tWhat do you want to do about it?\n\t(P)rint list again\n\t(V)iew files\n\t(D)elete files\n\t(A)dd files to valid list\n\t(S)kip\n";
			$input = readline( ">>> " );
		}

		$op = strtolower( $input{0} );
		$files = getEntries( $newFlat, $input );

		switch( $op )
		{
		case 'd':
			$access = $instance->getBestAccess( 'filetransfer' );

			foreach( $files as $file )
			{
				$access->deleteFile( $file );
				$newFlat = array_diff( $newFlat, (array) $file );
				unset( $new[$file] );
				echo "-- $file\n";
			}

			break;
		case 'a':
			$app = $instance->getApplication();

			foreach( $files as $file )
			{
				$version->recordFile( $new[$file], $file, $app );
				$newFlat = array_diff( $newFlat, (array) $file );
				unset( $new[$file] );
				echo "++ $file\n";
			}

			break;
		case 'v':
			$access = $instance->getBestAccess( 'filetransfer' );

			foreach( $files as $file )
			{
				$localName = $access->downloadFile( $file );
				passthru( EDITOR . " $localName" );
			}
			break;
		}
	} // }}}

	// Modified {{{
	$input = 'p';
	$modFlat = array_keys( $mod );

	while( $input != 's' && count( $mod ) )
	{
		echo "Modified files were found on remote host :\n";
		foreach( $modFlat as $key => $file )
			echo "\t[$key] $file\n";

		echo "\n\n";

		$input = 'z';
		while( stripos( 'pvcerus', $input{0} ) === false )
		{
			echo "\tWhat do you want to do about it? \n\t(P)rint list again\n\t(V)iew files\n\t(C)ompare files with versions in repository\n\t(E)dit files in place\n\t(R)eplace with version in repository\n\t(U)pdate hash to accept file version\n\t(S)kip\n";
			$input = readline( ">>> " );
		}

		$op = strtolower( $input{0} );
		$files = getEntries( $modFlat, $input );

		switch( $op )
		{
		case 'v':
			$access = $instance->getBestAccess( 'filetransfer' );

			foreach( $files as $file )
			{
				$localName = $access->downloadFile( $file );
				passthru( EDITOR . " $localName" );
			}
			break;
		case 'e':
			$access = $instance->getBestAccess( 'filetransfer' );
			$app = $instance->getApplication();

			foreach( $files as $file )
			{
				$localName = $access->downloadFile( $file );
				passthru( EDITOR . " $localName" );

				if( 'y' == readline( "Confirm file replacement? [y|n] " ) )
				{
					$hash = md5_file( $localName );
					if( $mod[$file] != $hash )
						$version->replaceFile( $hash, $file, $app );

					$access->uploadFile( $localName, $file );
					$modFlat = array_diff( $modFlat, (array) $file );
					unset( $mod[$file] );
					echo "== $file\n";
				}
			}
			break;
		case 'c':
			$access = $instance->getBestAccess( 'filetransfer' );
			$app = $instance->getApplication();

			foreach( $files as $file )
			{
				$realFile = $app->getSourceFile( $version, $file );
				$serverFile = $access->downloadFile( $file );

				$diff = DIFF;
				`$diff $realFile $serverFile`;
			}

			break;
		case 'r':
			$access = $instance->getBestAccess( 'filetransfer' );
			$app = $instance->getApplication();

			foreach( $files as $file )
			{
				$realFile = $app->getSourceFile( $version, $file );
				$access->uploadFile( $realFile, $file );

				$hash = md5_file( $realFile );
				if( $mod[$file] != $hash )
					$version->replaceFile( $hash, $file, $app );

				$modFlat = array_diff( $modFlat, (array) $file );
				unset( $mod[$file] );
				echo "== $file\n";
			}

			break;
		case 'u':
			$app = $instance->getApplication();

			foreach( $files as $file )
			{
				$version->replaceFile( $mod[$file], $file, $app );
				$modFlat = array_diff( $modFlat, (array) $file );
				unset( $mod[$file] );
				echo "++ $file\n";
			}

			break;
		}
	} // }}}

	// Deleted {{{
	$input = 'p';
	$delFlat = array_keys( $del );

	while( $input != 's' && count( $del ) )
	{
		echo "Deleted files were found on remote host :\n";
		foreach( $delFlat as $key => $file )
			echo "\t[$key] $file\n";

		echo "\n\n";

		$input = 'z';
		while( stripos( 'drs', $input{0} ) === false )
		{
			echo "\tWhat do you want to do about it? \n\t(R)estore version in repository\n\t(D)elete hash to accept file removal\n\t(S)kip\n";
			$input = readline( ">>> " );
		}

		$op = strtolower( $input{0} );
		$files = getEntries( $delFlat, $input );

		switch( $op )
		{
		case 'r':
			$access = $instance->getBestAccess( 'filetransfer' );
			$app = $instance->getApplication();

			foreach( $files as $file )
			{
				$realFile = $app->getSourceFile( $version, $file );
				$access->uploadFile( $realFile, $file );

				$hash = md5_file( $realFile );
				if( $del[$file] != $hash )
					$version->replaceFile( $hash, $file, $app );

				$delFlat = array_diff( $delFlat, (array) $file );
				unset( $del[$file] );
				echo "== $file\n";
			}

			break;
		case 'd':
			$access = $instance->getBestAccess( 'filetransfer' );
			$app = $instance->getApplication();

			foreach( $files as $file )
			{
				$version->removeFile( $file );

				$delFlat = array_diff( $delFlat, (array) $file );
				unset( $del[$file] );
				echo "-- $file\n";
			}

			break;
		}
	} // }}}
}

?>
