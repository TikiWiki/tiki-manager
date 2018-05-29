<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class Database
{
    private $instance;
    private $extensions = array();
    private $mysqlArgs = array();

    public $dbname;
    public $host;
    public $pass;
    public $type;
    public $user;

    public $dbLocalContent = "";

    public function __construct(Instance $instance, $credentials=array())
    {
        $this->instance = $instance;
        $this->access = $instance->getBestAccess('scripting');
        $this->setCredentials($credentials);
        $this->locateExtensions();
    }

    public function connect()
    {
        if (!($this->user && $this->host && $this->pass)) {
            throw new DatabaseError("Invalid credentials", 2);
        }

        try{
            $result = (int) $this->query('SELECT 1;');
            return $result === 1;
        } catch (DatabaseError $e) {
            if ($this->host === 'localhost') {
                $this->setMysqlArg('--protocol=TCP');
                $result = (int) $this->query('SELECT 1;');
                return $result === 1;
            }
            throw new DatabaseError($e->getMessage(), $e->getCode());
        }
        throw new DatabaseError("Can't connect to database", 2);
    }

    public function createAccess($user, $dbname, $pass=null)
    {
        $pass = $pass ?: Text_Password::create(12, 'unpronounceable');

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
        $db = new self($this->instance, $credentials);
        if ($db->testConnection()) {
            return $db;
        }
        return null;
    }

    public function createDatabase($name)
    {
        $sql = sprintf("CREATE DATABASE IF NOT EXISTS `%s`;", $name);
        $result = $this->query($sql);
        return $this;
    }

    static function createFromConfig($instance, $db_local_path)
    {
        if (! (file_exists($db_local_path) && filesize($db_local_path) > 0)) {
            return null;
        }

        $config = call_user_func(function($localFile){
            include($localFile);
            return array(
                'type' => $db_tiki,
                'host' => $host_tiki,
                'user' => $user_tiki,
                'pass' => $pass_tiki,
                'dbname' => $dbs_tiki,
            );
        }, $db_local_path);

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
        $host = $this->host === 'localhost'
            ? $this->host
            : '%';

        $sql = sprintf(
            'GRANT ALL ON `%s`.* TO `%s`@`%s`;',
            $database, $username, $host
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

        $command = new Host_Command('mysql', $args, $sql);
        $this->access->runCommand($command);

        if ($command->getReturn() !== 0) {
            throw new DatabaseError($command->getStderrContent(), 1);
        }
        return $command->getStdoutContent();
    }

    public function setCredentials($credentials=array())
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
        try{
            return $this->connect();
        } catch (DatabaseError $e) {
            error($e->getMessage());
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
            $user, $host
        );
        $result = $this->query($sql);
        return trim($result) === $user;
    }
}

class DatabaseError extends Exception
{
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
