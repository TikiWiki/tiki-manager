<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class Local_Host
{
    private static $resources = [];

    private $env;
    private $last_command_exit_code = 0;
    private $location;

    function __construct()
    {
        $this->env = $_ENV ?: [];
    }

    function chdir($location)
    {
        chdir($location);
        $this->location = $location;
    }

    function setenv($var, $value)
    {
        $this->env[$var] = $value;
    }

    function hasErrors()
    {
        return $this->last_command_exit_code !== 0;
    }

    function runCommand($command, $options = [])
    {
        $cwd = !empty($options['cwd']) ? $options['cwd'] : $this->location;
        $env = !empty($options['env']) ? $options['env'] : $this->env;

        $pipes = [];
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
            3 => ["pipe", "w"]
        ];

        $commandLine = $command->getFullCommand();
        $commandLine .= '; echo $? >&3';
        $process = proc_open($commandLine, $descriptorspec, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            return $command;
        }

        $stdin = $command->getStdin();
        if (is_resource($stdin)) {
            stream_copy_to_stream($stdin, $pipes[0]);
        }
        fclose($pipes[0]);

        $return = stream_get_contents($pipes[3]);
        $return = intval(trim($return));
        fclose($pipes[3]);

        $command->setStdout($pipes[1]);
        $command->setStderr($pipes[2]);
        $command->setProcess($process);
        $command->setReturn($return);

        return $command;
    }

    function runCommands($commands, $output = false)
    {
        if (! is_array($commands)) {
            $commands = func_get_args();
        }

        // TODO: There several calls to this function, each one with different
        //       parameters combination. It is hard to know when $output is a
        //       flag or a command. We have to change all calls to this function
        $commands = array_filter($commands, 'is_string');

        $commandPrefix = '';
        $commandPrefixArray = [];
        if ($this->location) {
            array_unshift($commandPrefixArray, 'cd ' . escapeshellarg($this->location));
        }

        foreach ($this->env as $name => $value) {
            array_unshift($commandPrefixArray, "export $name=$value");
        }

        if (count($commandPrefixArray)) {
            $commandPrefixArray[] = '';
            $commandPrefix = implode(' ;', $commandPrefixArray);
        }

        $contents = '';
        foreach ($commands as $cmd) {
            $cmd = $commandPrefix . $cmd . ' 2>&1';

            debug(var_export($this->env, true) . "\n" . $cmd);
            $ph = popen($cmd, 'r');

            $result = '';
            if (is_resource($ph)) {
                $result = trim(stream_get_contents($ph));
                $code = pclose($ph);
                $this->last_command_exit_code = $code;
                trim_output('LOCAL [' . date('Y-m-d H:i:s') . '] ' . $cmd . ' - return: ' . $code . (empty($result) ? '' : "\n" . $result));
                if ($code != 0) {
                    if ($output) {
                        warning(sprintf('%s [%d]', $cmd, $code));
                        error($result, $prefix = '    ');
                    }
                } else {
                    $contents .= (!empty($contents) ? "\n" : '') . $result;
                }

                debug($result, $prefix = "({$code})>>", "\n\n");
            }
        }

        return $contents;
    }

    function sendFile($localFile, $remoteFile)
    {
        $command = sprintf(
            'rsync -av %s %s',
            escapeshellarg($localFile),
            escapeshellarg($remoteFile)
        );
        $this->runCommands($command);
    }

    function receiveFile($remoteFile, $localFile)
    {
        $command = sprintf(
            'rsync -av %s %s',
            escapeshellarg($remoteFile),
            escapeshellarg($localFile)
        );
        $this->runCommands($command);
    }

    function openShell($workingDir = '')
    {
        if (empty($workingDir)) {
            return;
        }

        if (!is_dir($workingDir)) {
            $error = sprintf("Cannot connect: path (%s) is invalid or does not exist.\n", $workingDir);
            error($error);
            return;
        }

        $command = 'sh -c \'cd ' . $workingDir . '; exec ${SHELL:-sh}\'';
        passthru($command);
    }

    function rsync($args = [])
    {
        $return_val = -1;

        if (empty($args['src']) || empty($args['dest'])) {
            return $return_val;
        }

        $output = [];
        $command = sprintf(
            'rsync -aL --delete --exclude=.svn/tmp %s %s 2>&1',
            escapeshellarg($args['src']),
            escapeshellarg($args['dest'])
        );
        debug($command);

        $ph = popen($command, 'r');
        if (is_resource($ph)) {
            $output = trim(stream_get_contents($ph));
            ;
            $return_var = pclose($ph);
        }

        if ($return_var != 0) {
            warning($command);
            error("RSYNC exit code: $return_var");
            error($output);
        }

        debug($output, $prefix = "({$return_var})>>", "\n\n");
        return $return_var;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
