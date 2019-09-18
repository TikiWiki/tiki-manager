<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Libs\Host;

use TikiManager\Libs\Helpers\ApplicationHelper;

class Local
{
    private static $resources = [];

    private $env;
    private $last_command_exit_code = 0;
    private $location;

    public function __construct()
    {
        $this->env = $_ENV ?: [];
    }

    public function chdir($location)
    {
        chdir($location);
        $this->location = $location;
    }

    public function setenv($var, $value)
    {
        $this->env[$var] = $value;
    }

    public function hasErrors()
    {
        return $this->last_command_exit_code !== 0;
    }

    public function runCommand($command, $options = [])
    {
        $cwd = !empty($options['cwd']) ? $options['cwd'] : $this->location;
        $env = !empty($options['env']) ? $options['env'] : $this->env;

        if (empty($cwd)) {
            $cwd = null;
        }

        if (empty($env)) {
            $env = null;
        } else {
            $env = $this->mergeWithExistingEnvironmentVariables($env);
        }

        $pipes = [];
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
            3 => ["pipe", "w"]
        ];

        $commandLine = $command->getFullCommand();
        $commandLine .= ApplicationHelper::isWindows() ? '' : '; echo $? >&3';

        $process = proc_open($commandLine, $descriptorspec, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            return $command;
        }

        $stdin = $command->getStdin();
        if (is_resource($stdin)) {
            stream_copy_to_stream($stdin, $pipes[0]);
        }
        fclose($pipes[0]);

        $stdOut = stream_get_contents($pipes[1]);
        $strErr = stream_get_contents($pipes[2]);
        $return = stream_get_contents($pipes[3]);

        $return = intval(trim($return));
        fclose($pipes[3]);

        $command->setStdout($stdOut);
        $command->setStderr($strErr);
        $command->setProcess($process);
        $command->setReturn($return);

        return $command;
    }

    public function runCommands($commands, $output = false)
    {
        if (!is_array($commands)) {
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

        if (!ApplicationHelper::isWindows()) {
            foreach ($this->env as $name => $value) {
                array_unshift($commandPrefixArray, "export $name=$value");
            }

            if (count($commandPrefixArray)) {
                $commandPrefixArray[] = '';
                $commandPrefix = implode(' ;', $commandPrefixArray);
            }
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

    public function sendFile($localFile, $remoteFile)
    {
        if (ApplicationHelper::isWindows()) {
            $localFile = str_replace('/', DIRECTORY_SEPARATOR, $localFile);
            $remoteFile = str_replace('/', DIRECTORY_SEPARATOR, $remoteFile);

            $command = sprintf(
                'echo f | xcopy %s %s /k /y /q',
                escapeshellarg($localFile),
                escapeshellarg($remoteFile)
            );
        } else {
            $command = sprintf(
                'rsync -av %s %s',
                escapeshellarg($localFile),
                escapeshellarg($remoteFile)
            );
        }

        $this->runCommands($command);
    }

    public function receiveFile($remoteFile, $localFile)
    {
        if (ApplicationHelper::isWindows()) {
            $remoteFile = str_replace('/', DIRECTORY_SEPARATOR, $remoteFile);
            $localFile = str_replace('/', DIRECTORY_SEPARATOR, $localFile);

            $command = sprintf(
                'echo f | xcopy %s %s /k /y /q',
                escapeshellarg($remoteFile),
                escapeshellarg($localFile)
            );
        } else {
            $command = sprintf(
                'rsync -av %s %s',
                escapeshellarg($remoteFile),
                escapeshellarg($localFile)
            );
        }

        $this->runCommands($command);
    }

    public function openShell($workingDir = '')
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

    public function rsync($args = [], $options = [])
    {
        $return_val = -1;

        if (empty($args['src']) || empty($args['dest'])) {
            return $return_val;
        }

        $exclude = '';
        if (!empty($options['exclude'])) {
            $exclude = is_array($options['exclude']) ? $options['exclude'] : [$options['exclude']];
            $exclude = array_map(function($path) {
                return '--exclude=' . $path;
            }, $exclude);
            $exclude = implode(' ', $exclude);
        }

        $output = [];
        $command = sprintf(
            'rsync -aL --delete --exclude=.svn/tmp %s %s %s 2>&1',
            $exclude,
            escapeshellarg($args['src']),
            escapeshellarg($args['dest'])
        );
        debug($command);

        $ph = popen($command, 'r');
        if (is_resource($ph)) {
            $output = trim(stream_get_contents($ph));
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

    /**
     * Syncs one folder to another, similar to rsync but in windows environment
     * This method does not allow copy files and rename file on target.
     *
     * @param $remoteLocation
     * @param $localMirror
     * @param array $files
     * @param array $exclusions
     *
     * @return int The exit code
     */
    public function windowsSync($remoteLocation, $localMirror, $files = [], $exclusions = [])
    {

        $exclude = '';
        $returnVar = 0;
        $output = '';

        $remoteLocation = str_replace('/', DIRECTORY_SEPARATOR, $remoteLocation);
        $localMirror = str_replace('/', DIRECTORY_SEPARATOR, $localMirror);

        if (!empty($files)) {
            $files = implode(' ', array_map(function ($var) {
                $var = str_replace('/', DIRECTORY_SEPARATOR, $var);
                return escapeshellarg($var);
            }, $files));
        } else {
            $files = '';
        }

        if (!empty($exclusions)) {
            $exclude = '/xf ' . implode(' ', array_map(function ($var) {
                    $var = str_replace('/', DIRECTORY_SEPARATOR, $var);
                    return escapeshellarg($var);
            }, $exclusions));
        }

        $command = sprintf(
            'robocopy %s %s %s /e /purge /sl %s',
            escapeshellarg($remoteLocation),
            escapeshellarg($localMirror),
            $files,
            $exclude
        );

        debug($command);

        $ph = popen($command, 'r');
        if (is_resource($ph)) {
            $output = trim(stream_get_contents($ph));
            $returnVar = pclose($ph);
        }
        if ($returnVar > 8) {
            // Any value greater than 8 indicates that there was at least one failure during the copy operation.
            warning($command);
            error("ROBOCOPY exit code: $returnVar");
            error($output);
        }

        debug($output, $prefix = "({$returnVar})>>", PHP_EOL . PHP_EOL);
        return $returnVar;
    }

    /**
     * Merge the new variable list on top of the default environment variables
     *
     * @param array $newEnvironmentVariables
     * @return array
     */
    protected function mergeWithExistingEnvironmentVariables($newEnvironmentVariables)
    {
        $env = [];

        // getenv will only return all env variables starting 7.1.0
        foreach ($_SERVER as $variable => $tmp) {
            if ($value = getenv($variable)) {
                if ($value !== false && is_string($value)) {
                    $env[$variable] = $value;
                }
            }
        }
        foreach ($_ENV as $variable => $value) {
            if (is_string($value)) {
                $env[$variable] = $value;
            }
        }

        // merge (and if need override) the current environment variables
        foreach ($newEnvironmentVariables as $variable => $value) {
            $env[$variable] = (string)$value;
        }

        return $env;
    }
}

// vi: expandtab shiftwidth=4 softtabstop=4 tabstop=4
