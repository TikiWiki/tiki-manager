<?php

use PHPUnit\Framework\TestCase;


class SSH_HostTest extends TestCase
{
    const TARGET_HOST = '192.168.56.101';
    const TARGET_USER = 'root';
    const TARGET_PORT = 22;

    public function testCreateSSHHost()
    {
        $host = new SSH_Host(self::TARGET_HOST, self::TARGET_USER, self::TARGET_PORT);
        $this->assertTrue(is_object($host), 'SSH_Host is an object');
    }

    public function testGetSshCommandPrefix()
    {
        $host = new SSH_Host(self::TARGET_HOST, self::TARGET_USER, self::TARGET_PORT);
        $prefix = $host->getSshCommandPrefix();

        $expected = 'ssh'
            . ' -i ' . escapeshellarg(SSH_KEY)
            . ' -F ' . escapeshellarg(SSH_CONFIG)
            . ' -p ' . escapeshellarg(22)
            . ' '    . escapeshellarg(self::TARGET_USER . '@' . self::TARGET_HOST);

        $this->assertEquals($expected, $prefix);
    }

    public function testRunCommand()
    {
        $command = new Host_Command(':');
        $host = new SSH_Host(self::TARGET_HOST, self::TARGET_USER, self::TARGET_PORT);

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $return = $command->getReturn();

        $this->assertEquals(0, $return);
    }

    public function testRunCommandWithArgs()
    {
        $command = new Host_Command('echo', array('-n', 'Hello World'));
        $host = new SSH_Host(self::TARGET_HOST, self::TARGET_USER, self::TARGET_PORT);

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $return = $command->getReturn();

        $this->assertEquals(0, $return);
        $this->assertEquals('Hello World', $stdout);
    }

    public function testRunCommandWithStdin()
    {
        $command = new Host_Command('cat', array(), 'Hello World');
        $host = new SSH_Host(self::TARGET_HOST, self::TARGET_USER, self::TARGET_PORT);

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $return = $command->getReturn();

        $this->assertEquals(0, $return);
        $this->assertEquals('Hello World', $stdout);
    }

    public function testRunCommandWithArgsAndStdin()
    {
        $command = new Host_Command('head', array('-c', 5), "Hello\nWorld");
        $host = new SSH_Host(self::TARGET_HOST, self::TARGET_USER, self::TARGET_PORT);

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $stderr = $command->getStderrContent();
        $return = $command->getReturn();

        $this->assertEmpty($stderr);
        $this->assertEquals('Hello', $stdout);
        $this->assertEquals(0, $return);
    }

    public function testRunCommandWithEnvVars()
    {
        $command = new Host_Command('echo', array('-n', '$TEST1 $TEST2 $USER'));
        $host = new SSH_Host(self::TARGET_HOST, self::TARGET_USER, self::TARGET_PORT);
        $host->setenv('TEST1', 'Hello');
        $host->setenv('TEST2', 'World');

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $stderr = $command->getStderrContent();
        $return = $command->getReturn();

        $this->assertEmpty($stderr);
        $this->assertEquals('Hello World ' . self::TARGET_USER, $stdout);
        $this->assertEquals(0, $return);
    }

    public function testRunCommandWithEnvVarsAndStdin()
    {
        $command = new Host_Command('bash', array(), 'echo -n $TEST1\ $TEST2\ $USER');
        $host = new SSH_Host(self::TARGET_HOST, self::TARGET_USER, self::TARGET_PORT);
        $host->setenv('TEST1', 'Hello');
        $host->setenv('TEST2', 'World');

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $stderr = $command->getStderrContent();
        $return = $command->getReturn();

        $this->assertEmpty($stderr);
        $this->assertEquals('Hello World ' . self::TARGET_USER, $stdout);
        $this->assertEquals(0, $return);
    }

}
