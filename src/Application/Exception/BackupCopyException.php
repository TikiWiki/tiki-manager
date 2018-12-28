<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Application\Exception;

class BackupCopyException extends \Exception
{
    const RSYNC_ERROR = 1;
    private $RSYNC_ERRORS = [
        0  => 'Success',
        1  => 'Syntax or usage error',
        2  => 'Protocol incompatibility',
        3  => 'Errors selecting input/output files, dirs',
        4  => 'Requested  action not supported: an attempt was made to manipulate 64-bit files on a platform that cannot support them; or an option was specified that is supported by the client and not by the server.',
        5  => 'Error starting client-server protocol',
        6  => 'Daemon unable to append to log-file',
        10 => 'Error in socket I/O',
        11 => 'Error in file I/O',
        12 => 'Error in rsync protocol data stream',
        13 => 'Errors with program diagnostics',
        14 => 'Error in IPC code',
        20 => 'Received SIGUSR1 or SIGINT',
        21 => 'Some error returned by waitpid()',
        22 => 'Error allocating core memory buffers',
        23 => 'Partial transfer due to error',
        24 => 'Partial transfer due to vanished source files',
        25 => 'The --max-delete limit stopped deletions',
        30 => 'Timeout in data send/receive',
        35 => 'Timeout waiting for daemon connection'
    ];

    public function __construct($errors = '', $code = 1, $previous = null)
    {
        $message = $this->formatMessage($errors);
        parent::__construct($message, $code, $previous);
    }

    private function formatMessage($mixed)
    {
        if (is_string($mixed)) {
            return $mixed;
        }
        $EOL = "\r\n";

        $message = "! Backup has failed while downloading files into TRIM."
            . $EOL
            . $EOL . "!! Failures:"
            . $EOL;

        foreach ($mixed as $code => $errors) {
            $description = $this->RSYNC_ERRORS[$code];
            $message .= $EOL . sprintf('!!! (CODE: %3d) %s', $code, $description);

            foreach ($errors as $error) {
                $message .= $EOL . "* {$error}";
            }
            $message .= $EOL;
        }

        $message .= $EOL . '!! Reference'
            . $EOL . '[https://lxadm.com/Rsync_exit_codes|Rsync Exit Codes]';

        return $message;
    }
}
