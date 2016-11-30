<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

include_once dirname(__FILE__) . '/../../src/env_setup.php';

$values = array();

function menu()
{
    global $values;

    $values = array();
    echo "\nWhat do you want to do?\n";
    $values[] = 'add';
    echo "     add - Add a report receiver.\n";
    $values[] = 'remove';
    echo "  remove - Remove a report receiver.\n";
    $values[] = 'modify';
    echo "  modify - Modify a report receiver.\n";
    $values[] = 'send';
    echo "    send - Send updated reports.\n";
    $values[] = 'help';
    $values[] = 'exit';
    echo "    exit - Quit.\n";
}

function add()
{
    $report = new ReportManager;
    $instances = $report->getAvailableInstances();

    $selection = selectInstances($instances, "Which instances do you want to report on?\n");

    foreach ($selection as $instance) {
        $all = Instance::getInstances();

        $toAdd = selectInstances($all, "Which instances do you want to include in the report?\n");

        $report->reportOn($instance);
        $report->setInstances($instance, $toAdd);
    }
}

function modify()
{
    $report = new ReportManager;
    $instances = $report->getReportInstances();

    $selection = selectInstances($instances, "Which reports do you want to modify?\n");

    foreach ($selection as $instance) {
        $all = $report->getReportCandidates($instance);

        $toAdd = selectInstances($all, "Which instances do you want to include in the report?\n");

        $full = array_merge($report->getReportContent($instance), $toAdd);

        $report->setInstances($instance, $full);
    }
}

function remove()
{
    $report = new ReportManager;
    $instances = $report->getReportInstances();

    $selection = selectInstances($instances, "Which reports do you want to modify?\n");

    foreach ($selection as $instance) {
        $all = $report->getReportContent($instance);

        $toRemove = selectInstances($all, "Which instances do you want to remove from the report?\n");

        $report->removeInstances($instance, $toRemove);
    }
}

function send()
{
    $report = new ReportManager;
    $report->sendReports();
}

info("Note: Only Tiki instances can enable reports.");

do {
    menu();

    $request = promptUser('>>>', 'help', $values);

    switch ($request) {
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
    case 'help':
        break;
    }
}
while ($request != 'exit');

/*
$channel = new Channel('http://localhost/trunk/tiki-channel.php');

$report = new BackupReport;
$report->queueChannels($channel);

$channel->process();
*/

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
