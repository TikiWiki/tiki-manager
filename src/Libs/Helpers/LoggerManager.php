<?php

/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Libs\Helpers;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * LoggerManager class to manage logging functionality.
 * This class implements the Singleton pattern to ensure that only one
 * instance of the logger is created and reused throughout the application.
 */

class LoggerManager
{
    /**
     * @var LoggerManager|null $instance Holds the single instance of the LoggerManager class.
     */
    private static $instance = null;

    /**
     * @var Logger $logger Monolog logger instance for logging outputs.
     */
    private $logger;

    /**
     * LoggerManager constructor.
     * Initializes the Monolog logger with a rotating file handler and sets
     * the log file's maximum retention based on environment configuration.
     * The log file permissions are set to ensure proper access.
     */
    public function __construct()
    {
        $logMaxFiles = $_ENV['LOG_MAX_FILES'] ?? 30;
        $formatter = new LineFormatter(null, null, true, true);
        $handler = new RotatingFileHandler($_ENV['TRIM_OUTPUT'], $logMaxFiles, Logger::INFO);
        $handler->setFormatter($formatter);

        $this->logger = new Logger('trim_output');
        $this->logger->pushHandler($handler);

        if (file_exists($_ENV['TRIM_OUTPUT'])) {
            chmod($_ENV['TRIM_OUTPUT'], 0666);
        }
    }

    /**
     * Retrieves the single instance of LoggerManager.
     * If an instance does not exist, it initializes one.
     *
     * @return LoggerManager The singleton instance of LoggerManager.
     */
    public static function getInstance(): LoggerManager
    {
        if (self::$instance === null) {
            self::$instance = new LoggerManager();
        }

        return self::$instance;
    }

    /**
     * Logs information messages to the log file.
     * Uses the Monolog logger to log the output with the provided context.
     *
     * @param string $output The message or data to log.
     * @param array $context Additional context information to be logged.
     * @return void
     */
    public function logInfo(?string $output, array $context = []): void
    {
        $this->logger->info($output, $context);
    }
}
