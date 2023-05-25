<?php

namespace TikiManager\Tests\Host;

use PHPUnit\Framework\TestCase;
use TikiManager\Libs\Host\Command;
use TikiManager\Libs\Host\SSH;

/**
 * Class SSH_HostCommonTest
 * @group unit-ssh
 */
abstract class SSHHostCommonTest extends TestCase
{
    protected static $sshHost;
    protected static $sshUser;
    protected static $sshPass;
    protected static $sshPort;

    public static function setUpBeforeClass(): void
    {
        self::$sshHost = $_ENV['TEST_SSH_HOST'];
        self::$sshUser = $_ENV['TEST_SSH_USER'];
        self::$sshPass = $_ENV['TEST_SSH_PASS'];
        self::$sshPort = $_ENV['TEST_SSH_HOST'] ?? '22';
    }

    public function getInstance()
    {
        return new SSH(
            self::$sshHost,
            self::$sshUser,
            self::$sshPort
        );
    }

    public function testCreateSSHHost()
    {
        $host = $this->getInstance();
        $this->assertTrue(is_object($host), 'SSHHost is an object');
    }

    public function testRunCommand()
    {
        $host = $this->getInstance();
        $command = new Command(':');

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $return = $command->getReturn();

        $this->assertEquals(0, $return);
    }

    public function testRunCommandWithArgs()
    {
        $host = $this->getInstance();
        $command = new Command('echo', array('-n', 'Hello World'));

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $return = $command->getReturn();

        $this->assertEquals(0, $return);
        $this->assertEquals('Hello World', $stdout);
    }

    public function testRunCommandWithStdin()
    {
        $host = $this->getInstance();
        $command = new Command('cat', array(), 'Hello World');

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $return = $command->getReturn();

        $this->assertEquals(0, $return);
        $this->assertEquals('Hello World', $stdout);
    }

    public function testRunCommandWithArgsAndStdin()
    {
        $host = $this->getInstance();
        $command = new Command('head', array('-c', 5), "Hello\nWorld");

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
        $command = new Command('echo', array('-n', '$TEST1 $TEST2 $USER'));
        $host->setenv('TEST1', 'Hello');
        $host->setenv('TEST2', 'World');

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $stderr = $command->getStderrContent();
        $return = $command->getReturn();

        $this->assertEmpty($stderr);
        $this->assertEquals('Hello World ' . self::$sshUser, $stdout);
        $this->assertEquals(0, $return);
    }

    public function testRunCommandWithEnvVarsAndStdin()
    {
        $host = $this->getInstance();
        $command = new Command('bash', array(), 'echo -n $TEST1\ $TEST2\ $USER');
        $host->setenv('TEST1', 'Hello');
        $host->setenv('TEST2', 'World');

        $command->run($host);
        $stdout = $command->getStdoutContent();
        $stderr = $command->getStderrContent();
        $return = $command->getReturn();

        $this->assertEmpty($stderr);
        $this->assertEquals('Hello World ' . self::$sshUser, $stdout);
        $this->assertEquals(0, $return);
    }

    public function testRunCommandWithPhpCodeAsStdIn()
    {
        $host = $this->getInstance();
        $code = "<?php echo explode(' ', getenv('SSH_CONNECTION'))[2];";

        $command = new Command('php', array(), $code);
        $command->run($host);

        $this->assertEquals(0, $command->getReturn(), 'Command should exit 0');
        $this->assertEquals(self::$sshHost, $command->getStdoutContent());
        $this->assertEmpty($command->getStderrContent());
    }

    public function testRunCommandDoNotChangeStdinSize()
    {
        $host = $this->getInstance();
        $text = "Hello,\n\nThis text has 31 bytes.";

        $command = new Command('wc', array('-c'), $text);
        $command->run($host);

        $this->assertEquals(0, $command->getReturn(), 'Command should exit 0');
        $this->assertEquals(strlen($text), (int) $command->getStdoutContent());
        $this->assertEmpty($command->getStderrContent());

        $text = "Olá,\nO stdin não pode ter seu tamanho alterado.";

        $command = new Command('wc', array('-c'), $text);
        $command->run($host);

        $this->assertEquals(0, $command->getReturn(), 'Command should exit 0');
        $this->assertEquals(strlen($text), (int) $command->getStdoutContent());
        $this->assertEmpty($command->getStderrContent());
    }

    public function testRunCommandDoNotChangeStdinContent()
    {
        $host = $this->getInstance();
        $text = "Hello,\n\nThis text has 31 bytes.";

        $command = new Command('md5sum', array(), $text);
        $command->run($host);

        $this->assertEquals(0, $command->getReturn(), 'Command should exit 0');
        $this->assertStringStartsWith(md5($text), $command->getStdoutContent());
        $this->assertEmpty($command->getStderrContent());
    }

    public function testOldRunCommandsMethodStillWorks()
    {
        $host = $this->getInstance();

        $commands = ['echo "Hello World";'];
        $output = $host->runCommands($commands, true);
        $this->assertEquals('Hello World', $output);
    }

    public function testOldRunCommandsMethodStillWorksWithEnvVars()
    {
        $host = $this->getInstance();
        $host->setenv('TEST1', 'Hello');
        $host->setenv('TEST2', 'World');

        $commands = ['echo "$TEST1 $TEST2 $USER";'];
        $output = $host->runCommands($commands, true);

        $expected = 'Hello World ' . self::$sshUser;
        $this->assertEquals($expected, $output);
    }

    public function testOldRunCommandsMethodStillWorksWithPipeChar()
    {
        $host = $this->getInstance();
        $host->setenv('TEST1', 'HeLlO');
        $host->setenv('TEST2', 'WoRlD');

        $commands = ['echo "$TEST1 $TEST2 $USER" | tr "[:upper:]" "[:lower:]"'];
        $output = $host->runCommands($commands, true);

        $expected = 'hello world ' . strtolower(self::$sshUser);
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

        $command = new Command('cat', array($remotefile));
        $host->runCommand($command);
        $testdata = $command->getStdoutContent();
        $this->assertEquals($filedata, $testdata);

        $command = new Command('rm', array($remotefile));
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
        $filesize = strlen($filedata);

        $command = new Command('tee', array($remotefile), $filedata);
        $host->runCommand($command);
        $this->assertEquals(0, $command->getReturn());

        $command = new Command('stat', array('-c', '%s', $remotefile));
        $host->runCommand($command);
        $testdata = (int) $command->getStdoutContent();
        $this->assertEquals($filesize, $testdata, 'Could not create remote file');

        $command = new Command('cat', array($remotefile));
        $host->runCommand($command);
        $testdata = $command->getStdoutContent();
        $this->assertEquals($filedata, $testdata, 'Could not create remote file');

        $host->receiveFile($remotefile, $localfile);
        $testdata = file_get_contents($localfile, FILE_BINARY);
        $this->assertEquals($filedata, $testdata, 'The local copy differs from remote source.');

        $command = new Command('rm', array($remotefile));
        $host->runCommand($command);
        $testdata = $command->getReturn();
        $this->assertEquals(0, $testdata);

        unlink($localfile);
    }
}
