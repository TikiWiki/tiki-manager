<?php

// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include dirname(__FILE__) . "/../src/env_setup.php";


do {
	echo <<<MENU
What do you want to do?
[add] a report receiver
[remove] a report receiver
[modify] a report receiver
[send] updated reports
[exit]

MENU;
	$request = readline( '>>> ' );

	switch( $request ) {
	case 'add':
		add();
		break;
	case 'remove':
		remove();
		break;
	case 'modify':
		modify();
		break;
	case 'send':
		send();
		break;
	}
} while( $request != 'exit' );

function add() {
	$report = new ReportManager;
	$instances = $report->getAvailableInstances();
	
	echo "Which instances do you want to report on?";
	foreach( $instances as $key => $instance ) {
		echo "[$key] {$instance->name}\n";
	}

	$selection = readline( '>>> ' );
	$selection = getEntries( $instances, $selection );

	foreach( $selection as $instance ) {
		$instances = Instance::getInstances();
		
		echo "Which instances do you want to include in the report?\n";
		foreach( $instances as $key => $instance ) {
			echo "[$key] {$instance->name}\n";
		}

		$selection = readline( '>>> ' );
		$selection = getEntries( $instances, $selection );

		$report->reportOn( $instance );
		$report->setInstances( $instance, $instances );
	}
}

function send() {
	$report = new ReportManager;
	$report->sendReports();
}

/*
$channel = new Channel( 'http://localhost/trunk/tiki-channel.php' );

$report = new BackupReport;
$report->queueChannels( $channel );

$channel->process();
*/

?>
