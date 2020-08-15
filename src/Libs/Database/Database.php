<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Libs\Database;

use TikiManager\Config\App;
use TikiManager\Ext\Password;
use TikiManager\Libs\Host\Command;
use TikiManager\Application\Instance;
use TikiManager\Libs\Helpers\ApplicationHelper;
use TikiManager\Libs\Database\Exception\DatabaseErrorException;

class Database
{
    /** @var Instance */
    private $instance;
    private $extensions = array();
    private $mysqlArgs = array();

    public $dbname;
    public $host;
    public $pass;
    public $type;
    public $user;

    public $dbLocalContent = "";

    public function __construct(Instance $instance)
    {
        $this->setInstance($instance);
    }

    public function setInstance(Instance $instance)
    {
        $this->instance = $instance;
        $this->access = $instance->getBestAccess('scripting');
        $this->locateExtensions();

        return $this;
    }

    public function connect()
    {
        if (!($this->user && $this->host && $this->pass)) {
            throw new DatabaseErrorException("Invalid credentials", 2);
        }

        try {
            $result = (int) $this->query('SELECT 1;');
            return $result === 1;
        } catch (DatabaseErrorException $e) {
            if ($this->host === 'localhost') {
                $this->setMysqlArg('--protocol=TCP');
                $result = (int) $this->query('SELECT 1;');
                return $result === 1;
            }
            throw new DatabaseErrorException($e->getMessage(), $e->getCode());
        }
    }

    public function createAccess($user, $dbname, $pass = null)
    {
        $pass = $pass ?: Password::create(12, 'unpronounceable');

        $success = $this->createDatabase($dbname)
            && $this->createUser($user, $pass)
            && $this->grantRights($user, $dbname)
            && $this->finalize();

        $credentials = array(
            'dbname' => $dbname,
            'host'   => $this->host,
            'pass'   => $pass,
            'type'   => $this->type,
            'user'   => $user
        );

        $db = new self($this->instance);
        $db->setCredentials($credentials);
        if ($db->testConnection()) {
            return $db;
        }
        return null;
    }

    public function createDatabase($name)
    {
        $sql = sprintf('CREATE DATABASE IF NOT EXISTS `%s`;', $name);
        $result = $this->query($sql);
        return $this;
    }

    /**
     * Verify if the databases are equal
     * @param $database1 Database
     * @param $database2 Database
     * @return boolean
     */
    public static function compareDatabase($database1, $database2)
    {
        return (!is_null($database1) &&
            !is_null($database2) &&
            ($database1->host === $database2->host &&
                $database1->dbname === $database2->dbname &&
                $database1->user === $database2->user
            ));
    }

    public static function createFromConfig($instance, $db_local_path)
    {
        if (! (file_exists($db_local_path) && filesize($db_local_path) > 0)) {
            return null;
        }

        $config = self::getInstanceDataBaseConfig($db_local_path);

        $db = new self($instance);
        $db->host = $config['host'];
        $db->user = $config['user'];
        $db->pass = $config['pass'];
        $db->dbname = $config['dbname'];
        $db->type = $config['type'];

        if ($db->testConnection()) {
            $db->dbLocalContent = file_get_contents($db_local_path);
            return $db;
        }

        return null;
    }

    public function createUser($username, $password)
    {
        if ($username == $this->user) { //DB user is the root user
            return $this;
        }
        $host = $this->host === 'localhost'
            ? $this->host
            : '%';

        $sql = sprintf(
            'CREATE USER `%s`@`%s` IDENTIFIED BY "%s";',
            $username,
            $host,
            $password
        );
        $result = $this->query($sql);
        return $this;
    }

    public function databaseExists($dbname)
    {
        $dbname = $dbname ?: $this->dbname;
        $sql = sprintf('SHOW DATABASES LIKE "%s"', $dbname);
        $result = $this->query($sql);
        return trim($result) === $dbname;
    }

    public function finalize()
    {
        $sql = 'FLUSH PRIVILEGES;';
        $result = $this->query($sql);
        return $this;
    }

    public function getMaxUsernameLength()
    {
        $sql = 'SELECT CHARACTER_MAXIMUM_LENGTH'
            . ' FROM information_schema.COLUMNS'
            . ' WHERE TABLE_NAME="user"'
            .   ' AND TABLE_SCHEMA="mysql"'
            .   ' AND COLUMN_NAME="User"';

        $result = (int) $this->query($sql);
        return $result;
    }

    public function getUsableExtensions()
    {
        return array_intersect(
            $this->instance->getApplication()->getAcceptableExtensions(),
            $this->extensions
        );
    }

    public function grantRights($username, $database)
    {
        if ($username == $this->user) {
            return $this;
        }
        $host = $this->host === 'localhost'
            ? $this->host
            : '%';

        $sql = sprintf(
            'GRANT ALL ON `%s`.* TO `%s`@`%s`;',
            $database,
            $username,
            $host
        );
        $result = $this->query($sql);
        return $this;
    }

    public function locateExtensions()
    {
        if (empty($this->extensions)) {
            $modules = $this->instance->getExtensions();
            $expected = array('mysqli', 'mysql', 'pdo_mysql');
            $this->extensions = array_intersect($modules, $expected);
        }
        return $this->extensions;
    }

    public function query($sql)
    {
        $args = array(
            '-u', $this->user,
            '-p'. escapeshellarg($this->pass),
            '-h', $this->host,
            '-N',
            '-s'
        );
        $args = array_merge($args, $this->mysqlArgs);

        if (ApplicationHelper::isWindows()) {
            $args = implode(' ', $args);
            $command = 'echo ' . $sql . ' | mysql -f ' . $args;
            $command_result = $this->access->shellExec($command);
            return isset($command_result) ? rtrim($command_result, PHP_EOL) : 0;
        }

        $command = new Command('mysql', $args, $sql);
        $this->access->runCommand($command);

        if ($command->getReturn() !== 0) {
            throw new DatabaseErrorException($command->getStderrContent(), 1);
        }
        return $command->getStdoutContent();
    }

    public function setCredentials($credentials = array())
    {
        $credentials = array_filter($credentials, 'is_scalar');
        if (!empty($credentials['dbname'])) {
            $this->dbname = $credentials['dbname'];
        }
        if (!empty($credentials['host'])) {
            $this->host = $credentials['host'];
        }
        if (!empty($credentials['pass'])) {
            $this->pass = $credentials['pass'];
        }
        if (!empty($credentials['type'])) {
            $this->type = $credentials['type'];
        }
        if (!empty($credentials['user'])) {
            $this->user = $credentials['user'];
        }
        return $this;
    }

    private function setMysqlArg($arg)
    {
        $this->mysqlArgs[] = $arg;
    }

    public function testConnection()
    {
        try {
            return $this->connect();
        } catch (DatabaseErrorException $e) {
            App::get('io')->error($e->getMessage());
        }
        return false;
    }

    public function userExists($user)
    {
        $user = $user ?: $this->user;
        $host = $this->host === 'localhost' ? $this->host : '%';

        $sql = sprintf(
            'SELECT user FROM mysql.user'
            . ' WHERE user="%s" AND host="%s"',
            $user,
            $host
        );
        $result = $this->query($sql);
        return trim($result) === $user;
    }

    public static function getInstanceDataBaseConfig($db_local_path)
    {
        $getConfig = function ($db_local_path) {
            include($db_local_path);
            return array(
                'type' => $db_tiki,
                'host' => $host_tiki,
                'user' => $user_tiki,
                'pass' => $pass_tiki,
                'dbname' => $dbs_tiki,
            );
        };

        return $getConfig($db_local_path);
    }

    /**
     * @param Database|array $config
     * @return $this|Database|null
     */
    public function setupConnection()
    {
        $config = $this->instance->getDatabaseConfig();

        if ($config instanceof Database) {
            return $config;
        }

        list('host' => $dbHost, 'user' => $dbUser, 'pass' => $dbPass, 'database' => $dbName, 'prefix' => $dbPrefix) = $config;

        $this->host = $dbHost;
        $this->user = $dbUser;
        $this->pass = $dbPass;

        $types = $this->getUsableExtensions();
        $this->type = reset($types) ?: getenv('MYSQL_DRIVER');

        if (!$dbPrefix) {
            $this->dbname = $dbName;
            $this->instance->setDatabaseConfig($this);
            return;
        }

        $username = "{$dbPrefix}_user";
        $dbname = "{$dbPrefix}_db";

        try {
            $config = $this->createAccess($username, $dbname);
            $this->instance->setDatabaseConfig($config);
        } catch (DatabaseErrorException $e) {
            throw new \RuntimeException("Can't setup database!\nError: " . $e->getMessage());
        }
    }
}
// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
