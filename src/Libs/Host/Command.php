<?php
// Copyright (c) 2016, Avan.Tech, et. al.
// Copyright (c) 2008, Luis Argerich, Garland Foster, Eduardo Polidor, et. al.
// All Rights Reserved. See copyright.txt for details and a complete list of authors.
// Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See license.txt for details.

namespace TikiManager\Libs\Host;

use TikiManager\Libs\Host\Exception\CommandException;

/**
 * Prepare a shell command to run in a host object.
 * IMPORTANT!: All resources are cleaned and closed on __destruct
 */
class Command
{
    private $args;
    private $command;
    private $host;
    private $options;
    private $process;
    private $return;
    private $stderr;
    private $stdin;
    private $stdout;
    private $sudoUser;
    private $sudoWebroot;
    private $accessType;

    /**
     * Construct a Command object
     *
     * @param string $command    command to call
     * @param array  $args       command args as array
     * @param resource|string    the command stdin
     */
    public function __construct($command = ':', $args = [], $stdin = '')
    {
        $this->setCommand($command);
        $this->setArgs($args);
        $this->setStdin($stdin);
    }

    /**
     * Close all resources open by command
     */
    public function __destruct()
    {
        $this->finish();
    }

    /**
     * @return array Args used by command
     */
    public function getArgs()
    {
        return $this->prepareArgs($this->args);
    }

    /**
     * @return string Command called on shell
     */
    public function getCommand()
    {
        return $this->command ?: ':';
    }

    /**
     * @return string The command with args called on shell
     */
    public function getFullCommand()
    {
        $command = $this->getArgs();
        array_unshift($command, $this->getCommand());
        $fullCommand = join(' ', $command);

        if (!empty($this->sudoUser)) {
            $accessType = $this->getAccessType();
            $localCmd = strpos($fullCommand, 'ssh') === false;
            if (strpos($fullCommand, 'rsync') === 0) {
                $prefix = $accessType === 'local' && $localCmd ? 'sudo ' : '';
                $rsyncArgs = preg_split('/\s+/', $fullCommand);
                $rsyncCmd = $prefix . array_shift($rsyncArgs);
                $args = ["--rsync-path='sudo -u {$this->sudoUser} rsync'", "--chown='{$this->sudoUser}'"];
                $rsyncArgs = array_merge($args, $rsyncArgs);
                return implode(' ', array_merge([$rsyncCmd], $rsyncArgs));
            }
            if (!empty($this->sudoWebroot) && $accessType === 'local') {
                $fullCommand = sprintf("cd %s && %s", $this->sudoWebroot, $fullCommand);
            }
            return sprintf("sudo -u %s sh -c %s", escapeshellarg($this->sudoUser), escapeshellarg($fullCommand));
        }

        return $fullCommand;
    }

    public function wrapWithSudo($user = null, $webroot = null)
    {
        $this->sudoUser = $user;
        $this->sudoWebroot = $webroot;
    }

    public function setAccessType($type)
    {
        $this->accessType = $type;
    }

    public function getAccessType()
    {
        return $this->accessType ?? 'unknown';
    }

    /**
     * @return int The command exit code
     */
    public function getReturn()
    {
        return $this->return;
    }

    /**
     * @return array The process status, if exists
     */
    public function getStatus()
    {
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            return $status;
        }
        return [];
    }

    /**
     * @return resource The command stderr resource
     */
    public function getStderr()
    {
        return $this->stderr ?: null;
    }

    /**
     * @return string The command stderr as string
     */
    public function getStderrContent()
    {
        if (is_resource($this->stderr)) {
            return stream_get_contents($this->stderr);
        }
        return '';
    }

    /**
     * @return resource The command stdin resource
     */
    public function getStdin()
    {
        return $this->stdin ?: null;
    }

    /**
     * @return string The command stdin string
     */
    public function getStdinContent()
    {
        if (is_resource($this->stdin)) {
            return stream_get_contents($this->stdin);
        }
        return '';
    }

    /**
     * @return resource The command stdout resource
     */
    public function getStdout()
    {
        return $this->stdout ?: null;
    }

    /**
     * @return string The command stdout string
     */
    public function getStdoutContent()
    {
        if (is_resource($this->stdout)) {
            return stream_get_contents($this->stdout);
        }
        return '';
    }

    /**
     * Prepare arguments before executing command
     *
     * @param  mixed $args String or positional array
     * @return array       Arguments prepared
     */
    public function prepareArgs($args)
    {
        $result = [];
        if (is_string($args)) {
            $args = preg_split('/  */', $args);
        }
        if (empty($args)) {
            return $result;
        }

        foreach ($args as $arg) {
            if (is_string($arg)) {
                $arg = trim($arg);
                if (strpos($arg, '-') === 0) {
                    if (strpos($arg, '=') > -1) {
                        $arg = explode('=', $arg, 2);
                        $arg = "{$arg[0]}=" . escapeshellarg($arg[1]);
                    } elseif (strpos($arg, ' ') > -1) {
                        $arg = explode(' ', $arg, 2);
                        $arg = "{$arg[0]} " . escapeshellarg($arg[1]);
                    }
                } else {
                    $arg = escapeshellarg($arg);
                }
            } elseif (is_callable($arg)) {
                $arg = $arg();
            }
            $result[] = $arg;
        }
        return $result;
    }

    /**
     * @param  mixed $args String or positional array
     * @return array       Arguments prepared
     */
    public function setArgs($args)
    {
        return $this->args = $args;
    }

    /**
     * @param string $command The command path
     */
    public function setCommand($command)
    {
        return $this->command = $command ?: ':';
    }

    /**
     * Set the host where the command will be executed
     */
    public function setHost($host)
    {
        return $this->host = $host;
    }

    /**
     * Set options like current path and environment to host
     */
    public function setOptions($options)
    {
        return $this->options = $options ?: [];
    }

    /**
     * Set option like current path and environment to host
     */
    public function setOption($name, $value)
    {
        $this->options = $this->options ?: [];
        return $this->options[$name] = $value;
    }

    /**
     * @param resource $process The process running the command
     */
    public function setProcess($process)
    {
        if (is_resource($process) && get_resource_type($process) === 'process') {
            return $this->process = $process;
        }
    }

    /**
     * @param int $return The command exitcode
     */
    public function setReturn($return)
    {
        return $this->return = $return;
    }

    /**
     * @param resource|string $stderr The command stderr
     */
    public function setStderr($stderr)
    {
        if (is_object($stderr) && method_exists($stderr, '__toString')) {
            $stderr = strval($stderr);
        }
        if (is_string($stderr)) {
            $res = fopen('php://memory', 'r+');
            fwrite($res, $stderr);
            rewind($res);
            $stderr = $res;
        }
        if (is_resource($stderr)) {
            return $this->stderr = $stderr;
        }
        return null;
    }

    /**
     * @param resource|string $stdin The content to command stdin
     */
    public function setStdin($stdin)
    {
        if (is_object($stdin) && method_exists($stdin, '__toString')) {
            $stdin = strval($stdin);
        }
        if (is_string($stdin)) {
            $res = fopen('php://memory', 'r+');
            fwrite($res, $stdin);
            rewind($res);
            $stdin = $res;
        }
        if (is_resource($stdin)) {
            return $this->stdin = $stdin;
        }
        return null;
    }

    /**
     * @param resource|string $stdout The command stdout
     */
    public function setStdout($stdout)
    {
        if (is_object($stdout) && method_exists($stdout, '__toString')) {
            $stdout = strval($stdout);
        }
        if (is_string($stdout)) {
            $res = fopen('php://memory', 'r+');
            fwrite($res, $stdout);
            rewind($res);
            $stdout = $res;
        }
        if (is_resource($stdout)) {
            return $this->stdout = $stdout;
        }
        return null;
    }

    /**
     * Closes all resources opened by command (process, stdin, stdout, stderr).
     * This method is also called on __destruct, this means the resources will
     * also be closed on PHP garbage collecting.
     */
    public function finish()
    {
        is_resource($this->process) && proc_close($this->process);
        is_resource($this->stdin) && fclose($this->stdin);
        is_resource($this->stdout) && fclose($this->stdout);
        is_resource($this->stderr) && fclose($this->stderr);
    }

    /**
     * Runs this command in a host object
     *
     * @param null $host A host object
     * @param array $options
     * @return Command  $this
     * @throws CommandException
     */
    public function run($host = null, $options = [])
    {
        $host = $this->host ?: $host;
        $options = $this->options ?: $options;

        if (is_resource($this->process) || !is_null($this->return)) {
            throw new CommandException("TikiManager\Libs\Host\Command cannot run twice", 1);
        }
        $host->runCommand($this, $options);
        return $this;
    }
}
