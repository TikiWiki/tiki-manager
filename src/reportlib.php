<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

define('SQL_SELECT_AVAIL_INSTANCES', "
SELECT
    instance.instance_id 
FROM 
    instance 
LEFT JOIN
    report_receiver ON instance.instance_id = report_receiver.instance_id
WHERE
    report_receiver.instance_id IS NULL
;");

define('SQL_SELECT_REPORT_CONTENT', "
SELECT
    instance_id 
FROM 
    report_content 
WHERE
    receiver_id = :id
;");

define('SQL_SELECT_REPORT_CANDIDATES', "
SELECT
    instance.instance_id 
FROM 
    instance
LEFT JOIN
    report_content ON instance.instance_id = report_content.instance_id
    AND report_content.receiver_id = :id
WHERE
    report_content.instance_id IS NULL
;");

define('SQL_SELECT_REPORT_SENDERS', "
SELECT
    instance_id, user, pass
FROM
    report_receiver
;");

define('SQL_INSERT_REPORT_RECEIVER', "
INSERT INTO
    report_receiver
VALUES
    (:id, :user, :pass)
;");

define('SQL_INSERT_REPORT_CONTENT', "
INSERT INTO
    report_content
VALUES
    (:instance, :id)
;");

define('SQL_DELETE_REPORT_CONTENT_BY_RECEIVER', "
DELETE FROM
    report_content
WHERE
    receiver_id = :id
;");

define('SQL_DELETE_REPORT_CONTENT_BY_INSTANCE', "
DELETE FROM
    report_content
WHERE
    receiver_id = :id AND instance_id = :inst
;");

class ReportManager
{
    function getAvailableInstances()
    {
        $result = query(SQL_SELECT_AVAIL_INSTANCES);

        $records = $result->fetchAll();
        $ids = array_map('reset', $records);

        return $this->buildInstancesArray($ids);
    }

    function getReportContent($instance)
    {
        $result = query(SQL_SELECT_REPORT_CONTENT, array(':id' => $instance->id));

        $records = $result->fetchAll();
        $ids = array_map('reset', $records);

        return $this->buildInstancesArray($ids);
    }

    function getReportCandidates(Instance $instance)
    {
        $result = query(SQL_SELECT_REPORT_CANDIDATES, array(':id' => $instance->id));

        $records = $result->fetchAll();
        $ids = array_map('reset', $records);

        return $this->buildInstancesArray($ids);
    }

    /**
     * Build an array of instances objects from a array
     * of instances ids.
     *
     * @param array $ids
     * @return array
     */
    protected function buildInstancesArray($ids)
    {
        $instances = array();
        $instance = new Instance;

        foreach ($ids as $id)
            $instances[$id] = $instance->getInstance($id);

        return $instances;
    }

    function reportOn($instance)
    {
        $instance->getApplication()->installProfile('profiles.tiki.org', 'TRIM_Report_Receiver');
        $password = $instance->getBestAccess('scripting')->runPHP(
            dirname(__FILE__) . '/../scripts/tiki/remote_setup_channels.php',
            array($instance->webroot, $instance->contact)
        );
        
        query(SQL_INSERT_REPORT_RECEIVER,
            array(':id' => $instance->id, ':user' => 'trim_user', ':pass' => $password));
    }

    function setInstances($receiver, $instances)
    {
        query(SQL_DELETE_REPORT_CONTENT_BY_RECEIVER,
            array(':id' => $receiver->id));

        foreach ($instances as $instance) {
            query(SQL_INSERT_REPORT_CONTENT,
                array(':instance' => $receiver->id, ':id' => $instance->id));
        }
    }

    function sendReports()
    {
        $backup = new BackupReport;

        foreach ($this->getReportSenders() as $row) {
            $instance = $row['instance'];
            $content = $this->getReportContent($instance);

            $channel = new Channel($instance->getWebUrl('tiki-channel.php'));
            $channel->setAuthentication($row['user'], $row['pass']);
            $backup->queueChannels($channel, $content);
            $channel->process();
        }
    }

    private function getReportSenders()
    {
        $out = array();
        $senders = query(SQL_SELECT_REPORT_SENDERS);

        while ($row = $senders->fetch()) {
            $instance = new Instance;
            $instance = $instance->getInstance($row['instance_id']);
            
            $row['instance'] = $instance;
            $out[] = $row;
        }

        return $out;
    }

    function getReportInstances()
    {
        $instances = array();

        foreach ($this->getReportSenders() as $row)
            $instances[$row['instance_id']] = $row['instance'];

        return $instances;
    }

    function removeInstances($receiver, $instances)
    {
        foreach ($instances as $instance) {
            query(SQL_DELETE_REPORT_CONTENT_BY_INSTANCE,
                array(':id' => $receiver->id, ':inst' => $instance->id));
        }
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
