<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

class Local_Host
{
    private static $resources = array();

    private $location;
    private $env = array();

    private $last_command_exit_code = 0;

    function chdir($location)
    {
        $this->location = $location;
    }

    function setenv($var, $value)
    {
        $this->env[$var] = $value;
    }

    function hasErrors() {
        return $this->last_command_exit_code !== 0;
    }

    function runCommands($commands, $output=false)
    {
        if (! is_array($commands))
            $commands = func_get_args();

        if ($output && !is_string($output)) {
            array_pop($commands);
        }

        if ($this->location)
            array_unshift($commands, 'cd ' . escapeshellarg($this->location));

        foreach ($this->env as $name => $value)
            array_unshift($commands, "export $name=$value");

        $contents = '';
        foreach ($commands as $cmd) {
            $cmd .= ' 2>&1';

            debug($cmd);
            $ph = popen($cmd, 'r');

            $result = '';
            if (is_resource($ph)) {
                $result = trim(stream_get_contents($ph));
                $code = pclose($ph);
                $this->last_command_exit_code = $code;

                trim_output("LOCAL $result");
                if ($code != 0) {
                    if($output) {
                        warning(sprintf('%s [%d]', $cmd, $code));
                        error($result, $prefix='    ');
                    }
                } else {
                    $contents .= (!empty($contents) ? "\n" : '') . $result;
                }

                debug($result, $prefix="({$code})>>", "\n\n");
            }
        }

        return $contents;
    }

    function sendFile($localFile, $remoteFile)
    {
        $command = sprintf('rsync -av %s %s',
            escapeshellarg($localFile), escapeshellarg($remoteFile));
        $this->runCommands($command);
    }

    function receiveFile($remoteFile, $localFile)
    {
        $command = sprintf('rsync -av %s %s',
            escapeshellarg($remoteFile), escapeshellarg($localFile));
        $this->runCommands($command);
    }

    function openShell($workingDir = '')
    {
    }

    function rsync($remoteLocation, $localMirror)
    {
        $output = array();
        $return_val = -1;
        $command = sprintf('rsync -aL --delete %s %s 2>&1',
            escapeshellarg($remoteLocation), escapeshellarg($localMirror));
        debug($command);

        $ph = popen($command, 'r');
        if (is_resource($ph)) {
            $output = trim(stream_get_contents($ph));;
            $return_var = pclose($ph);
        }

        if ($return_var != 0) {
            warning($command);
            error("RSYNC exit code: $return_var");
            error($output);
        }

        debug($output, $prefix="({$return_var})>>", "\n\n");
        return $return_var;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
