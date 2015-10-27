<?php

// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . "/../../src/env_setup.php";


do {
	echo "Note: Only instances running Tiki can enable reports.\n\n";
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

	$selection = selectInstances( $instances, "Which instances do you want to report on?\n" );

	foreach( $selection as $instance ) {
		$all = Instance::getInstances();

		$toAdd = selectInstances( $all, "Which instances do you want to include in the report?\n" );

		$report->reportOn( $instance );
		$report->setInstances( $instance, $toAdd );
	}
}

function modify() {
	$report = new ReportManager;
	$instances = $report->getReportInstances();

	$selection = selectInstances( $instances, "Which reports do you want to modify?\n" );

	foreach( $selection as $instance ) {
		$all = $report->getReportCandidates( $instance );

		$toAdd = selectInstances( $all, "Which instances do you want to include in the report?\n" );

		$full = array_merge( $report->getReportContent( $instance ), $toAdd );

		$report->setInstances( $instance, $full );
	}
}

function remove() {
	$report = new ReportManager;
	$instances = $report->getReportInstances();

	$selection = selectInstances( $instances, "Which reports do you want to modify?\n" );

	foreach( $selection as $instance ) {
		$all = $report->getReportContent( $instance );

		$toRemove = selectInstances( $all, "Which instances do you want to remove from the report?\n" );

		$report->removeInstances( $instance, $toRemove );
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
