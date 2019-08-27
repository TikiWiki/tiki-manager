<?php

namespace TikiManager\Libs\Helpers;

/**
 * Class PDO_WRAPPER
 * Wrapper around PDO to ease some functionalities related and error catching
 */
class PDOWrapper extends \PDO
{
    private $die_on_exception_thrown;
    private $has_extended_debug;

    /**
     * PDO_WRAPPER constructor.
     * @param $dsn
     * @param $username
     * @param $passwd
     * @param array $options
     */
    public function __construct($dsn, $user = '', $password = '', $options = [])
    {
        $this->die_on_exception_thrown = $_ENV['PDO_DIE_ON_EXCEPTION_THROWN'];
        $this->has_extended_debug = $_ENV['PDO_EXTENDED_DEBUG'];

        if (empty($options)) {
            $options = [
                \PDO::ATTR_TIMEOUT   =>  $_ENV['PDO_ATTR_TIMEOUT'],
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ];
        }
        return parent::__construct($dsn, $user, $password, $options);
    }

    /**
     * DB exec function wrapper
     * @param string $statement
     * @return int|void
     */
    public function exec($statement)
    {
        try {
            return parent::exec($statement);
        } catch (\PDOException $e) {
            $this->showError($e, $statement);
        }
    }

    /**
     * DB query function wrapper
     * @param string $statement
     * @return int|void
     */
    public function query($statement)
    {
        try {
            return parent::query($statement);
        } catch (\PDOException $e) {
            $this->showError($e, $statement);
        }
    }

    /**
     * Show PDO triggered exception
     * @param $exception
     */
    private function showError($exception, $query = '')
    {
        $message = $exception->getMessage(). "\n";
        if ($this->has_extended_debug) {
            $message .= "$query\n";
        }

        error($message);

        if ($this->die_on_exception_thrown) {
            die();
        }
    }
}
