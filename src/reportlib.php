<?php

Class ReportManager
{
	function getAvailableInstances() {
		$result = query( "
			SELECT instance.instance_id 
			FROM 
				instance 
				LEFT JOIN report_receiver ON instance.instance_id = report_receiver.instance_id
			WHERE
				report_receiver.instance_id IS NULL " );

		$records = sqlite_fetch_all( $result );
		$ids = array_map( 'reset', $records );

		$instance = new Instance;

		return array_map( array( $instance, 'getInstance' ), $ids );
	}

	function getReportContent( $instance ) {
		$result = query( "
			SELECT instance_id 
			FROM 
				report_content 
			WHERE
				receiver_id = :id", array( ':id' => $instance->id ) );

		$records = sqlite_fetch_all( $result );
		$ids = array_map( 'reset', $records );

		$instance = new Instance;

		return array_map( array( $instance, 'getInstance' ), $ids );
	}

	function reportOn( $instance ) {
		$instance->getApplication()->installProfile( 'profiles.tikiwiki.org', 'TRIM_Report_Receiver' );
		$password = $instance->getBestAccess('scripting')->runPHP( dirname(__FILE__) . '/../scripts/remote_setup_channels.php', array( $instance->webroot, $instance->contact ) );
		
		query('INSERT INTO report_receiver VALUES(:id, :user, :pass)',
			array( ':id' => $instance->id, ':user' => 'trim_user', ':pass' => $password ) );
	}

	function setInstances( $receiver, $instances ) {
		query( 'DELETE FROM report_content WHERE receiver_id = :id', array( ':id' => $receiver->id ) );

		foreach( $instances as $instance ) {
			query( 'INSERT INTO report_content VALUES(:instance, :id)', array( ':instance' => $receiver->id, ':id' => $instance->id ) );
		}
	}

	function sendReports() {
		$backup = new BackupReport;

		$senders = query( 'SELECT instance_id, user, pass FROM report_receiver' );
		while( $row = sqlite_fetch_array( $senders ) ) {
			$instance = new Instance;
			$instance = $instance->getInstance( $row['instance_id'] );

			$content = $this->getReportContent( $instance );

			$channel = new Channel( $instance->getWebUrl( 'tiki-channel.php' ) );
			$channel->setAuthentication( $row['user'], $row['pass'] );
			$backup->queueChannels( $channel, $content );
			$channel->process();
		}
	}
}

?>
