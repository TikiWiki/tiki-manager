<?php

class Host_Command
{
    private $args;
    private $command;
    private $process;
    private $return;
    private $stderr;
    private $stdin;
    private $stdout;

    public function __construct($command=':', $args=array(), $stdin='')
    {
        $this->setCommand($command);
        $this->setArgs($args);
        $this->setStdin($stdin);
    }

    public function __destruct() {
        is_resource($this->process) && proc_close($this->process);
    }

    public function getArgs()
    {
        return $this->args ?: array();
    }
    
    public function getCommand()
    {
        return $this->command ?: ':';
    }

    public function getFullCommand()
    {
        $command = $this->getArgs();
        array_unshift($command, $this->getCommand());
        return join(' ', $command);
    }

    public function getReturn()
    {
        return $this->return;
    }

    public function getStatus() {
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            return $status;
        }
    }

    public function getStderr()
    {
        return $this->stderr ?: null;
    }

    public function getStderrContent()
    {
        if (is_resource($this->stderr)) {
            return stream_get_contents($this->stderr);
        }
        return '';
    }

    public function getStdin()
    {
        return $this->stdin ?: null;
    }

    public function getStdout() {
        return $this->stdout ?: null;
    }

    public function getStdoutContent()
    {
        if (is_resource($this->stdout)) {
            return stream_get_contents($this->stdout);
        }
        return '';
    }

    public function prepareArgs($args)
    {
        $result = array();
        if (is_string($args)) {
            $args = preg_split('/  */', $args);
        }
        else if(is_object($args)) {
            $args = get_object_vars($args);
        }
        if (empty($args)){
            return $result;
        }
        $args = array_flatten($args, true);
        foreach ($args as $arg) {
            if (strpos($arg, '-') === 0) {
                if(strpos($arg, '=') > -1) {
                    $arg = explode('=', $arg, 2);
                    $arg = "{$arg[0]}=" . escapeshellarg($arg[1]);
                }
            }
            else {
                $arg = escapeshellarg($arg);
            }
            $result[] = $arg;
        }
        return $result;
    }

    public function setArgs($args)
    {
        $args = $this->prepareArgs($args);
        return $this->args = $args;
    }
    
    public function setCommand($command)
    {
        return $this->command = $command ?: ':';
    }

    public function setProcess($process)
    {
        if(is_resource($process) && get_resource_type($process) === 'process') {
            return $this->process = $process;
        }
    }

    public function setReturn($return)
    {
        return $this->return = $return;
    }

    public function setStderr($stderr)
    {
        if (is_object($stderr) && method_exists($stderr, '__toString')){
            $stderr = strval($stderr);
        }
        if (is_string($stderr)) {
            $res = fopen('php://memory','r+');
            fwrite($res, $stderr);
            rewind($res);
            $stderr = $res;
        }
        if (is_resource($stderr)) {
            return $this->stderr = $stderr;
        }
        return null;
    }

    public function setStdin($stdin)
    {
        if (is_object($stdin) && method_exists($stdin, '__toString')){
            $stdin = strval($stdin);
        }
        if (is_string($stdin)) {
            $res = fopen('php://memory','r+');
            fwrite($res, $stdin);
            rewind($res);
            $stdin = $res;
        }
        if (is_resource($stdin)) {
            return $this->stdin = $stdin;
        }
        return null;
    }

    public function setStdout($stdout)
    {
        if (is_object($stdout) && method_exists($stdout, '__toString')){
            $stdout = strval($stdout);
        }
        if (is_string($stdout)) {
            $res = fopen('php://memory','r+');
            fwrite($res, $stdout);
            rewind($res);
            $stdout = $res;
        }
        if (is_resource($stdout)) {
            return $this->stdout = $stdout;
        }
        return null;
    }

    public function finish(){
        is_resource($this->process) && proc_terminate($this->process);
        is_resource($this->stdin) && fclose($this->stdin);
        is_resource($this->stdout) && fclose($this->stdout);
        is_resource($this->stderr) && fclose($this->stderr);
    }

    public function run($host)
    {
        if(is_resource($this->process)) {
            proc_close($this->process);
        }
        return $host->runCommand($this);
    }
}
