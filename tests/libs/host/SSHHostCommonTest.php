<?php

use PHPUnit\Framework\TestCase;


abstract class SSH_HostCommonTest extends TestCase
{
    const TARGET_HOST = '192.168.56.101';
    const TARGET_USER = 'root';
    const TARGET_PORT = 22;

    public function getInstance()
    {
        return new SSH_Host(
            self::TARGET_HOST,
            self::TARGET_USER,
            self::TARGET_PORT
        );
    }

    public function testCreateSSHHost()
    {
        $host = $this->getInstance();
        $this->assertTrue(is_object($host), 'SSH_Host is an object');
    }

    public function testRunCommand()
    {
        $host = $this->getInstance();
        $command = new Host_Command(':');

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $return = $command->getReturn();

        $this->assertEquals(0, $return);
    }

    public function testRunCommandWithArgs()
    {
        $host = $this->getInstance();
        $command = new Host_Command('echo', array('-n', 'Hello World'));

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $return = $command->getReturn();

        $this->assertEquals(0, $return);
        $this->assertEquals('Hello World', $stdout);
    }

    public function testRunCommandWithStdin()
    {
        $host = $this->getInstance();
        $command = new Host_Command('cat', array(), 'Hello World');

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $return = $command->getReturn();

        $this->assertEquals(0, $return);
        $this->assertEquals('Hello World', $stdout);
    }

    public function testRunCommandWithArgsAndStdin()
    {
        $host = $this->getInstance();
        $command = new Host_Command('head', array('-c', 5), "Hello\nWorld");

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
        $host = $this->getInstance();
        $command = new Host_Command('echo', array('-n', '$TEST1 $TEST2 $USER'));
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
        $host = $this->getInstance();
        $command = new Host_Command('bash', array(), 'echo -n $TEST1\ $TEST2\ $USER');
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

    public function testOldRunCommandsMethodStillWorks()
    {
        $host = $this->getInstance();

        $commands = array('echo "Hello World";');
        $output = $host->runCommands($commands, true);
        $this->assertEquals('Hello World', $output);
    }

    public function testOldRunCommandsMethodStillWorksWithEnvVars()
    {
        $host = $this->getInstance();
        $host->setenv('TEST1', 'Hello');
        $host->setenv('TEST2', 'World');

        $commands = array('echo "$TEST1 $TEST2 $USER";');
        $output = $host->runCommands($commands, true);

        $expected = 'Hello World ' . self::TARGET_USER;
        $this->assertEquals($expected, $output);
    }

    public function testOldRunCommandsMethodStillWorksWithPipeChar()
    {
        $host = $this->getInstance();
        $host->setenv('TEST1', 'HeLlO');
        $host->setenv('TEST2', 'WoRlD');

        $commands = array('echo "$TEST1 $TEST2 $USER" | tr "[:upper:]" "[:lower:]"');
        $output = $host->runCommands($commands, true);

        $expected = 'hello world ' . strtolower(self::TARGET_USER);
        $this->assertEquals($expected, $output);
    }

    public function testCopyLocalFileToRemoteHost()
    {
        $host = $this->getInstance();

        $localfile = '/tmp/test-trim-local-file.txt';
        $remotefile = '/tmp/test-trim-remote-file.txt';

        $filedata = uniqid();
        file_put_contents($localfile, $filedata);
        $host->sendFile($localfile, $remotefile);

        $command = new Host_Command('cat', array($remotefile));
        $host->runCommand($command);
        $testdata = $command->getStdoutContent();
        $this->assertEquals($filedata, $testdata);

        $command = new Host_Command('rm', array($remotefile));
        $host->runCommand($command);
        $testdata = $command->getReturn();
        $this->assertEquals(0, $testdata);

        unlink($localfile);
    }

    public function testCopyRemoteFileToLocalHost()
    {
        $host = $this->getInstance();

        $localfile = '/tmp/test-trim-local-file.txt';
        $remotefile = '/tmp/test-trim-remote-file.txt';

        $filedata = uniqid();
        $command = new Host_Command('tee', array($remotefile), $filedata);
        $host->runCommand($command);
        $this->assertEquals(0, $command->getReturn());

        $command = new Host_Command('cat', array($remotefile));
        $host->runCommand($command);
        $testdata = $command->getStdoutContent();
        $this->assertEquals($filedata, $testdata, 'Could not create remote file');

        $host->receiveFile($remotefile, $localfile);
        $testdata = file_get_contents($localfile);
        $this->assertEquals($filedata, $testdata, 'The local copy differs from remote source.');

        $command = new Host_Command('rm', array($remotefile));
        $host->runCommand($command);
        $testdata = $command->getReturn();
        $this->assertEquals(0, $testdata);

        unlink($localfile);
    }
}
