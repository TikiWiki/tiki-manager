<?php

function perform_archive_cleanup()
{
	$files = glob( ARCHIVE_FOLDER . "/*.tar.bz2" );

	foreach( $files as $file )
	{
		$name = basename( $file );
		if( preg_match( "/^(\d+)_(\d{4})-(\d{2})-(\d{2})_(\d{2}):(\d{2}):(\d{2})\.tar\.bz2$/", $name, $parts ) )
		{
			list( $match, $instance, $year, $month, $date, $hour, $minute, $second ) = $parts;

			// Preserve one backup per month, the one on the first
			if( $date == '01' )
				continue;

			$time = mktime( (int) $hour, (int) $minute, (int) $second, (int) $month, (int) $date, (int) $year );
			$daysAgo = (time() - $time) / (24*3600);

			// Keep all backups on Sunday for a month
			$day = date( 'D', $time );
			if( $day == 'Sun' && $daysAgo <= 31 )
				continue;

			// Destroy backups after a week
			if( $daysAgo > 7 )
				unlink( $file );
		}
	}
}

?>
