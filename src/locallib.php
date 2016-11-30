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

    function chdir($location)
    {
        $this->location = $location;
    }

    function setenv($var, $value)
    {
        $this->env[$var] = $value;
    }

    function runCommands($commands, $output = false)
    {
        if (! is_array($commands))
            $commands = func_get_args();

        if ($this->location)
            array_unshift($commands, 'cd ' . escapeshellarg($this->location));

        foreach ($this->env as $name => $value)
            array_unshift($commands, "export $name=$value");

        $fullcommand = implode(
            ($output ? ' 2>/dev/null' : ' 2>> /tmp/trim.output') . ' && ',
            $commands
        );
        $fullcommand .= ($output ? ' 2>/dev/null' : ' 2>> /tmp/trim.output');

        $contents = array();
        $ph = popen($fullcommand, 'r');
        if (is_resource($ph)) {
            $contents = trim(stream_get_contents($ph));
            trim_output("LOCAL $contents");

            if (($rc = pclose($ph)) != 0)
                warning(sprintf('%s [%d]', $fullcommand, $rc));
        }

        return $contents;
    }

    function sendFile($localFile, $remoteFile)
    {
        $command = sprintf('rsync -a %s %s',
            escapeshellarg($localFile), escapeshellarg($remoteFile));
        $this->runCommands($command);
    }

    function receiveFile($remoteFile, $localFile)
    {
        $command = sprintf('rsync -a %s %s',
            escapeshellarg($remoteFile), escapeshellarg($localFile));
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

        $ph = popen($command, 'r');
        if (is_resource($ph))
            $return_var = pclose($ph);

        if ($return_var != 0)
            info("RSYNC exit code: $return_var");
        return $return_var;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
