<?php

namespace TikiManager\Libs\Helpers;

use PDOStatement;
use BadMethodCallException;
use PDO;
use TikiManager\Config\App;

/**
 * Class PDO_WRAPPER
 * Wrapper around PDO to ease some functionalities related and error catching
 */
class PDOWrapper
{
    /** @var PDO */
    private $pdo;

    private $dieOnException;
    private $hasExtendedDebug;

    /**
     * PDO_WRAPPER constructor.
     * @param $dsn
     * @param $user
     * @param $password
     * @param array $options
     */
    public function __construct($dsn, $user = '', $password = '', $options = [])
    {
        $this->dieOnException = $_ENV['PDO_DIE_ON_EXCEPTION_THROWN'];
        $this->hasExtendedDebug = $_ENV['PDO_EXTENDED_DEBUG'];

        if (empty($options)) {
            $options = [
                \PDO::ATTR_TIMEOUT   =>  $_ENV['PDO_ATTR_TIMEOUT'],
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_STRINGIFY_FETCHES => true // To keep consistency between PHP7.4 and PHP8.1 casting
            ];
        }

        $this->pdo = new PDO($dsn, $user, $password, $options);
    }

    /**
     * DB exec function wrapper
     * @param string $statement
     * @return int|void
     */
    public function exec(string $statement)
    {
        try {
            return $this->pdo->exec($statement);
        } catch (\PDOException $e) {
            $this->showError($e, $statement);
        }
    }

    /**
     * DB query function wrapper
     * @param string $query
     * @param int $fetchMode
     * @param mixed $fetchModeArgs
     * @return bool|PDOStatement
     */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs)
    {
        try {
            $args = func_get_args();
            return $this->pdo->query(...$args);
        } catch (\PDOException $e) {
            $this->showError($e, $query);
            return false;
        }
    }

    /**
     * Show PDO triggered exception
     * @param $exception
     */
    private function showError($exception, $query = '')
    {
        $message = $exception->getMessage(). "\n";
        if ($this->hasExtendedDebug) {
            $message .= "$query\n";
        }

        if ($this->dieOnException) {
            throw $exception;
        }

        App::get('io')->error($message);
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->pdo, $name)) {
            return call_user_func_array(array($this->pdo,$name), $arguments);
        }

        throw new BadMethodCallException();
    }
}
