<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Report;

use TikiManager\Report\Backup as ReportBackup;
use TikiManager\Application\Instance;

class Manager
{
    const SQL_SELECT_AVAIL_INSTANCES = <<<SQL
SELECT
    instance.instance_id 
FROM 
    instance 
LEFT JOIN
    report_receiver ON instance.instance_id = report_receiver.instance_id
WHERE
    report_receiver.instance_id IS NULL
;
SQL;

    const SQL_SELECT_REPORT_CONTENT = <<<SQL
SELECT
    instance_id 
FROM 
    report_content 
WHERE
    receiver_id = :id
;
SQL;

    const SQL_SELECT_REPORT_CANDIDATES = <<<SQL
SELECT
    instance.instance_id 
FROM 
    instance
LEFT JOIN
    report_content ON instance.instance_id = report_content.instance_id
    AND report_content.receiver_id = :id
WHERE
    report_content.instance_id IS NULL
;
SQL;

    const SQL_SELECT_REPORT_SENDERS = <<<SQL
SELECT
    instance_id, user, pass
FROM
    report_receiver
;
SQL;

    const SQL_INSERT_REPORT_RECEIVER = <<<SQL
INSERT INTO
    report_receiver
VALUES
    (:id, :user, :pass)
;
SQL;

    const SQL_INSERT_REPORT_CONTENT = <<<SQL
INSERT INTO
    report_content
VALUES
    (:instance, :id)
;
SQL;

    const SQL_DELETE_REPORT_CONTENT_BY_RECEIVER = <<<SQL
DELETE FROM
    report_content
WHERE
    receiver_id = :id
;
SQL;

    const SQL_DELETE_REPORT_CONTENT_BY_INSTANCE = <<<SQL
DELETE FROM
    report_content
WHERE
    receiver_id = :id AND instance_id = :inst
;
SQL;

    public function getAvailableInstances()
    {
        $result = query(self::SQL_SELECT_AVAIL_INSTANCES);

        $records = $result->fetchAll();
        $ids = array_map('reset', $records);

        return $this->buildInstancesArray($ids);
    }

    public function getReportContent($instance)
    {
        $result = query(self::SQL_SELECT_REPORT_CONTENT, [':id' => $instance->id]);

        $records = $result->fetchAll();
        $ids = array_map('reset', $records);

        return $this->buildInstancesArray($ids);
    }

    public function getReportCandidates(Instance $instance)
    {
        $result = query(self::SQL_SELECT_REPORT_CANDIDATES, [':id' => $instance->id]);

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
        $instances = [];

        foreach ($ids as $id) {
            $instances[$id] = Instance::getInstance($id);
        }

        return $instances;
    }

    public function reportOn($instance)
    {
        $instance->getApplication()->installProfile('profiles.tiki.org', 'TRIM_Report_Receiver');
        $password = $instance->getBestAccess('scripting')->runPHP(
            dirname(__FILE__) . '/../../scripts/tiki/remote_setup_channels.php',
            [$instance->webroot, $instance->contact]
        );

        query(
            self::SQL_INSERT_REPORT_RECEIVER,
            [':id' => $instance->id, ':user' => 'trim_user', ':pass' => $password]
        );
    }

    public function setInstances($receiver, $instances)
    {
        query(
            self::SQL_DELETE_REPORT_CONTENT_BY_RECEIVER,
            [':id' => $receiver->id]
        );

        foreach ($instances as $instance) {
            query(
                self::SQL_INSERT_REPORT_CONTENT,
                [':instance' => $receiver->id, ':id' => $instance->id]
            );
        }
    }

    public function sendReports()
    {
        $backup = new ReportBackup();

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
        $out = [];
        $senders = query(self::SQL_SELECT_REPORT_SENDERS);

        while ($row = $senders->fetch()) {
            $instance = new Instance;
            $instance = $instance->getInstance($row['instance_id']);

            $row['instance'] = $instance;
            $out[] = $row;
        }

        return $out;
    }

    public function getReportInstances()
    {
        $instances = [];

        foreach ($this->getReportSenders() as $row) {
            $instances[$row['instance_id']] = $row['instance'];
        }

        return $instances;
    }

    public function removeInstances($receiver, $instances)
    {
        foreach ($instances as $instance) {
            query(
                self::SQL_DELETE_REPORT_CONTENT_BY_INSTANCE,
                [':id' => $receiver->id, ':inst' => $instance->id]
            );
        }
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
