<?php

class BackupReport
{
	function queueChannels( Channel $channel, $instances ) {
		$channel->push( 'trim_backup_summary', array( 'body' => $this->getSummary( $instances ) ) );
		
		foreach( $instances as $instance ) {
			$channel->push( 'trim_backup_detail', array(
				'instance_name' => $instance->name,
				'body' => $this->getDetails( $instance ),
			) );
		}
	}

	private function getSummary( $instances ) {
		$this->getDiskUsage( $used, $total, $free );
		$humanUsed = $this->humanReadableSize( $used, 1 );
		$humanTotal = $this->humanReadableSize( $total, 1 );
		$humanFree = $this->humanReadableSize( $free, 1 );

		$usedRatio = $used / $total * 100;
		$availableRatio = $free / $total * 100;

		$date = date( 'F j, Y' );
		$summary = <<<SUM
! Summary
{img src="http://chart.apis.google.com/chart?cht=p3&chd=t:$usedRatio%2C$availableRatio&chs=500x150&chl=Used%7CAvailable&chtt=Disk+Usage" imalign=right}
__Disk usage:__ $humanUsed / $humanTotal ( $humanFree free )
__Date:__ $date

SUM;

		$summary .= <<<OUT

{maketoc}

OUT;

		return $summary;
	}

	private function hasValidBackup( $instance ) {
		$archives = $instance->getArchives();
		if( count( $archives ) == 0 ) {
			return false;
		}

		// Last backup within 24 hours
		if( time() - filemtime( $archives[0] ) > 24*3600 ) {
			return false;
		}

		$backups = array_map( 'filesize', $archives );
		$average = array_sum( $backups ) / count( $backups );

		// Last backup not below 90% of the average
		if( $backups[0] < 0.9 * $average ) {
			return false;
		}

		return true;
	}

	private function getDiskUsage( &$used, &$total, &$available ) {
		$path = escapeshellarg( ARCHIVE_FOLDER );
		$out = `df $path | awk '/^\// {print $3,$2,$4}'`;

		list( $used, $total, $available ) = explode( ' ', $out );
	}

	private function humanReadableSize( $value, $init = 0 ) {
		$unit = array( 'K', 'M','G','T','P' );
		for( $i = 0; $init > $i; ++$i ) {
			array_shift( $unit );
		}
		$used = '';

		while( $value >= 1000 && count( $unit ) > 0 )
		{
			$value /= 1024;
			$used = array_shift( $unit );
		}

		return round( $value, 1 ) . $used . "B";
	}

	private function getDetails( $instance ) {
		$archives = $instance->getArchives();

		if( count( $archives ) > 0 ) {
			$valid = $this->hasValidBackup( $instance ) ? 'accept' : 'exclamation';
			$backups = array_map( 'filesize', $archives );
			$latest = $this->humanReadableSize( $backups[0] );

			$sizes = implode( '%2C', $this->getRelativeSizes( $backups ) );

			$title = urlencode( "{$instance->name} Backup Sizes" );
			$date = date( 'F j, Y H:i:s', filemtime( $archives[0] ) );
			$detail = <<<DET

! {img src=pics/icons/$valid.png} {$instance->name}
{img src="http://chart.apis.google.com/chart?cht=bvg&chd=t:$sizes&chs=500x150&chtt=$title" imalign=right}
__Website:__ [{$instance->weburl}]
__Contact:__ [mailto:{$instance->contact}|{$instance->contact}]
__Last backup:__ $date
__Backup size:__ $latest

{FADE(label="Backups")}
||__Date__|__Size__|__Location__

DET;
			foreach( $archives as $key => $file ) {
				$date = date( 'Y-m-d H:i:s', filemtime( $file ) );
				$size = $this->humanReadableSize( $backups[$key] );
				$detail .= "$date|$size|$file\n";
			}

			$detail .= <<<DET
||
{FADE}
DET;
		} else {
			$detail = <<<DET

! {img src=pics/icons/exclamation.png} {$instance->name}
__Website:__ [{$instance->weburl}]
__Contact:__ [mailto:{$instance->contact}|{$instance->contact}]
__Last backup:__ ~~red:NEVER~~
DET;
		}

		return $detail;
	}

	private function getRelativeSizes( $sizes ) {
		$low = array_reduce( $sizes, 'min', $sizes[0] );
		$high = array_reduce( $sizes, 'max', $sizes[0] );
		$high = $high + 0.1 * $high;
		$low = $low - 0.2 * $low;

		$span = $high - $low;

		$percs = array();
		foreach( $sizes as $size ) {
			$percs[] = ( $size - $low ) / $span * 100;
		}

		return $percs;
	}
}
