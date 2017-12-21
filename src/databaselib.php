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
    private $host;

    function __construct($host, $masterUser, $masterPassword) // {{{
    {
        $args = array();
        $this->host = $host;
        
        $args[] = "-h " . escapeshellarg($host);
        $args[] = "-u " . escapeshellarg($masterUser);
        if ($masterPassword)
            $args[] = '-p' . escapeshellarg($masterPassword);

        $this->args = implode(' ', $args);
    } // }}}

    function getMaxUsernameLength(Instance $instance) {
        $access = $instance->getBestAccess('scripting');
        $sql = "'"
            . 'SELECT CHARACTER_MAXIMUM_LENGTH'
            . ' FROM information_schema.COLUMNS'
            . ' WHERE TABLE_NAME="user"'
            .   ' AND TABLE_SCHEMA="mysql"'
            .   ' AND COLUMN_NAME="User"'
            . "'";
        $cmd = "mysql {$this->args} -N -s -e {$sql}";
        return $access->shellExec($cmd, $output=true);
    }

    function createDatabase(Instance $instance, $name) // {{{
    {
        // FIXME : Not safemode compatible
        $access = $instance->getBestAccess('scripting');
        $access->shellExec("mysqladmin {$this->args} create $name", $output=true);
        return $access->host->hasErrors() === false;
    } // }}}

    function createUser(Instance $instance, $username, $password) // {{{
    {
        // FIXME : Not FTP compatible
        $u = $this->escapeMysqlString($username);
        $p = $this->escapeMysqlString($password);
        $query = escapeshellarg("CREATE USER '$u'@'{$this->host}' IDENTIFIED BY '$p';");

        $access = $instance->getBestAccess('scripting');
        $access->shellExec("echo $query | mysql {$this->args}", $output=true);
        return $access->host->hasErrors() === false;
    } // }}}

    function grantRights(Instance $instance, $username, $database) // {{{
    {
        // FIXME : Not FTP compatible
        $u = $this->escapeMysqlString($username);
        $d = $this->escapeMysqlString($database);
        $query = escapeshellarg("GRANT ALL ON `$d`.* TO '$u'@'{$this->host}';");

        $access = $instance->getBestAccess('scripting');
        $access->shellExec("echo $query | mysql {$this->args}", $output=true);
        return $access->host->hasErrors() === false;
    } // }}}

    function finalize(Instance $instance) // {{{
    {
        // FIXME : Not FTP compatible
        $access = $instance->getBestAccess('scripting');
        $access->shellExec("mysqladmin {$this->args} reload");
        return $access->host->hasErrors() === false;
    } // }}}

    function getSupportedExtensions() // {{{
    {
        return array('mysqli', 'mysql', 'pdo_mysql');
    } // }}}

    protected function escapeMysqlString($string)
    {
        if (function_exists('mysql_escape_string')){
            return mysql_escape_string($string);
        }

        // Fallback for new versions of PHP where DB connection is required
        // See:
        // http://php.net/manual/en/function.mysql-real-escape-string.php#101248
        // https://dev.mysql.com/doc/refman/5.5/en/mysql-real-escape-string.html
        // https://github.com/mysql/mysql-server/blob/71f48ab393bce80a59e5a2e498cd1f46f6b43f9a/mysys/charset.c
        $search = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
        $replace = array("\\\\","\\0","\\n", "\\r", "\\'", '\"', "\\Z");
        return str_replace($search, $replace, $string);
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
