<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

interface Database_Adapter
{
    function createDatabase(Instance $instance, $name);

    function createUser(Instance $instance, $username, $password);

    function finalize(Instance $instance);

    function grantRights(Instance $instance, $username, $database);

    function getSupportedExtensions();
}

class Database
{
    private $instance;
    private $adapter;
    private $extensions = array();

    public $host;
    public $user;
    public $pass;
    public $dbname;
    public $type;

    function __construct(Instance $instance, Database_Adapter $adapter) // {{{
    {
        $this->instance = $instance;
        $this->adapter = $adapter;

        $this->locateExtensions();
    } // }}}

    private function locateExtensions() // {{{
    {
        $modules = $this->instance->getExtensions();

        $this->extensions = array_intersect(
            $modules,
            array(
                'mysqli',
                'mysql',
                'pdo_mysql',
                'sqlite',
                'pdo_sqlite',
            )
        );
    } // }}}

    function getUsableExtensions() // {{{
    {
        return array_intersect(
            $this->instance->getApplication()->getAcceptableExtensions(),
            $this->extensions,
            $this->adapter->getSupportedExtensions()
        );
    } // }}}

    function createAccess($prefix) // {{{
    {
        $this->pass = Text_Password::create(12, 'unpronounceable');
        $this->user = "{$prefix}_user";
        $this->dbname = "{$prefix}_db";

        return $this->adapter->createUser($this->instance, $this->user, $this->pass)
            && $this->adapter->createDatabase($this->instance, $this->dbname)
            && $this->adapter->grantRights($this->instance, $this->user, $this->dbname)
            && $this->adapter->finalize($this->instance);
    } // }}}
}

class Database_Adapter_Dummy implements Database_Adapter
{
    function __construct() // {{{
    {
    } // }}}

    function createDatabase(Instance $instance, $name) // {{{
    {
    } // }}}

    function createUser(Instance $instance, $username, $password) // {{{
    {
    } // }}}

    function grantRights(Instance $instance, $username, $database) // {{{
    {
    } // }}}

    function finalize(Instance $instance) // {{{
    {
    } // }}}

    function getSupportedExtensions() // {{{
    {
        return array('mysqli', 'mysql', 'pdo_mysql', 'sqlite', 'pdo_sqlite');
    } // }}}
}

class Database_Adapter_Mysql implements Database_Adapter
{
    private $args;
    private $hostname;
    private $con;

    function __construct($hostname, $username, $password) // {{{
    {
        $this->hostname = $hostname;
        $this->con = new PDO("mysql:host=$hostname", $username, $password); 
    } // }}}

    function getMaxUsernameLength(Instance $instance) {
        $sql = 'SELECT CHARACTER_MAXIMUM_LENGTH'
            . ' FROM information_schema.COLUMNS'
            . ' WHERE TABLE_NAME="user"'
            .   ' AND TABLE_SCHEMA="mysql"'
            .   ' AND COLUMN_NAME="User"';

        $result = $this->con->query($sql);
        $return = $result ? $result->fetchColumn(0) : false;
        debug($sql, '[PDO]');
        debug($return, '[PDO]');
        return $return;
    }

    function createDatabase(Instance $instance, $name) // {{{
    {
        $sql = sprintf("CREATE DATABASE `%s`;", $name);
        $statement = $this->con->prepare($sql);
        $result = $statement->execute();
        if(!$result) {
            warning(vsprintf("[%s:%d] %s", $statement->errorInfo()));
        }
        return $result;
    } // }}}

    function createUser(Instance $instance, $username, $password) // {{{
    {
        $statement = $this->con->prepare("CREATE USER ?@? IDENTIFIED BY ?;");
        $result = $statement->execute(array($username, $this->hostname, $password));
        if(!$result) {
            warning(vsprintf("[%s:%d] %s", $statement->errorInfo()));
        }
        return $result;
    } // }}}

    function grantRights(Instance $instance, $username, $database) // {{{
    {
        $sql = sprintf("GRANT ALL ON `%s`.* TO ?@?;", $database);
        $statement = $this->con->prepare($sql);
        $result = $statement->execute(array($username, $this->hostname));
        if(!$result) {
            warning(vsprintf("[%s:%d] %s", $statement->errorInfo()));
        }
        return $result;
    } // }}}

    function finalize(Instance $instance) // {{{
    {
        $sql = "FLUSH PRIVILEGES;";
        $statement = $this->con->prepare("FLUSH PRIVILEGES;");
        $result = $statement->execute();
        if(!$result) {
            warning(vsprintf("[%s:%d] %s", $statement->errorInfo()));
        }
        return $result;
    } // }}}

    function getSupportedExtensions() // {{{
    {
        return array('mysqli', 'mysql', 'pdo_mysql');
    } // }}}
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
