<?php

namespace TikiManager\Command\Exception;

class InvalidCronTimeException extends \Exception
{
    protected $message = <<<TXT
Invalid cron time expression. Please provide a value in the format:

*    *    *    *    *
|    |    |    |    |
|    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
|    |    |    +---------- month (1 - 12)
|    |    +--------------- day of month (1 - 31)
|    +-------------------- hour (0 - 23)
+------------------------- min (0 - 59)

Examples:
0 * * * *   - Run hourly
0 */8 * * * - Run every 8 hours
0 0 1 * *   - Run every first day of month
0 0 * * 0   - Run every Sunday at midnight
TXT;
}
