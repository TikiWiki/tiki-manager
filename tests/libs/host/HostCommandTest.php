<?php

use PHPUnit\Framework\TestCase;

class Host_CommandTest extends TestCase
{
    public function testCreateCommand()
    {
        $command = new Host_Command('cat', [], 'Hello World');
        $this->assertTrue(is_object($command));
        $this->assertEquals('cat', $command->getCommand());
        $this->assertEquals('cat', $command->getFullCommand());
    }

    public function testPassStdinAsString()
    {
        $command = new Host_Command('cat', [], 'Hello World');

        $stdin = $command->getStdin();
        $this->assertTrue(is_resource($stdin), 'stdin is a resource');

        $stdin = stream_get_contents($stdin);
        $this->assertEquals('Hello World', $stdin);
    }

    public function testPassStdinAsResource()
    {
        $res = fopen(__FILE__, 'r');
        $command = new Host_Command('cat', [], $res);

        $stdin = $command->getStdin();
        $this->assertTrue(is_resource($stdin), 'stdin is a resource');

        $stdin = stream_get_contents($stdin);
        $this->assertEquals(file_get_contents(__FILE__), $stdin);
        fclose($res);

        $res = fopen(__FILE__, 'r');
        $command = new Host_Command('cat', [], $res);
        $stdin = $command->getStdinContent();
        $this->assertEquals(file_get_contents(__FILE__), $stdin);

        fclose($res);
    }

    public function testPassStdoutAsString()
    {
        $command = new Host_Command('head', ['-n1'], 'TikiWiki');
        $pipes = [];
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        $commandLine = $command->getFullCommand();
        $process = proc_open($commandLine, $descriptorspec, $pipes);
        stream_copy_to_stream($command->getStdin(), $pipes[0]);
        fclose($pipes[0]);

        $process = $command->setProcess($process);

        // setting stdout as string
        $command->setStdout(stream_get_contents($pipes[1]));
        $command->setStderr($pipes[2]);

        $stdout = $command->getStdout();
        $this->assertTrue(is_resource($stdout), 'stdout is a resource');

        $stdout = stream_get_contents($stdout);
        $this->assertEquals('TikiWiki', $stdout);
    }

    public function testStdoutAsResource()
    {
        $command = new Host_Command('head', ['-n1'], 'TikiWiki');
        $pipes = [];
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        $commandLine = $command->getFullCommand();
        $process = proc_open($commandLine, $descriptorspec, $pipes);
        stream_copy_to_stream($command->getStdin(), $pipes[0]);
        fclose($pipes[0]);

        $process = $command->setProcess($process);

        // setting stdout as resource
        $command->setStdout($pipes[1]);
        $command->setStderr($pipes[2]);

        $stdout = $command->getStdout();
        $this->assertTrue(is_resource($stdout), 'stdout is a resource');

        $stdout = stream_get_contents($stdout);
        $this->assertEquals('TikiWiki', $stdout);
    }

    public function testPassStderrAsString()
    {
        // -l1 is a wrong parameter for head command
        $command = new Host_Command('head', ['-l1'], 'TikiWiki');
        $pipes = [];
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
            3 => ["pipe", "w"],
        ];

        $commandLine = $command->getFullCommand();
        $commandLine .= '; echo $? >&3'; // https://bugs.php.net/bug.php?id=29123

        $process = proc_open($commandLine, $descriptorspec, $pipes);
        stream_copy_to_stream($command->getStdin(), $pipes[0]);
        fclose($pipes[0]);

        $process = $command->setProcess($process);

        // setting stdout as string
        $command->setStdout($pipes[1]);
        $command->setStderr(stream_get_contents($pipes[2]));

        $return = stream_get_contents($pipes[3]);
        $return = intval(trim($return));
        fclose($pipes[3]);

        $stderr = $command->getStderr();
        $this->assertTrue(is_resource($stderr), 'stderr is a resource');

        $stderr = $command->getStderrContent($stderr);
        $this->assertNotEmpty($stderr, 'there is a stderr to be displayed');
    }

    public function testPassStderrAsResource()
    {
        // -l1 is a wrong parameter for head command
        $command = new Host_Command('head', ['-l1'], 'TikiWiki');
        $pipes = [];
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
            3 => ["pipe", "w"],
        ];

        $commandLine = $command->getFullCommand();
        $commandLine .= '; echo $? >&3'; // https://bugs.php.net/bug.php?id=29123

        $process = proc_open($commandLine, $descriptorspec, $pipes);
        stream_copy_to_stream($command->getStdin(), $pipes[0]);
        fclose($pipes[0]);

        $process = $command->setProcess($process);

        // setting stdout as string
        $command->setStdout($pipes[1]);
        $command->setStderr($pipes[2]);

        $return = stream_get_contents($pipes[3]);
        $return = intval(trim($return));
        fclose($pipes[3]);

        $stderr = $command->getStderr();
        $this->assertTrue(is_resource($stderr), 'stderr is a resource');

        $stderr = $command->getStderrContent($stderr);
        $this->assertNotEmpty($stderr, 'there is a stderr to be displayed');
    }

    public function testPreparedArgs()
    {
        $command = new Host_Command('cat', ['-n', '/my/file']);
        $args = $command->getArgs();

        $this->assertEquals(2, count($args));
        $this->assertEquals("-n", $args[0]);
        $this->assertEquals("'/my/file'", $args[1]);

        $command = new Host_Command('cat', ['-n', '/my/weird file']);
        $args = $command->getArgs();
        $this->assertEquals("-n", $args[0]);
        $this->assertEquals(2, count($args));
        $this->assertEquals("'/my/weird file'", $args[1]);

        $command = new Host_Command('fakecom', ['--name=/my/weird file']);
        $args = $command->getArgs();
        $this->assertEquals(1, count($args));
        $this->assertEquals("--name='/my/weird file'", $args[0]);
    }

    public function testResourcesAreCleanedOnFinish()
    {
        $command = new Host_Command('head', ['-n1'], 'TikiWiki');
        $pipes = [];
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        $commandLine = $command->getFullCommand();
        $process = proc_open($commandLine, $descriptorspec, $pipes);
        stream_copy_to_stream($command->getStdin(), $pipes[0]);
        fclose($pipes[0]);

        $process = $command->setProcess($process);
        $stdin = $command->getStdin();
        $stdout = $command->setStdout($pipes[1]);
        $stderr = $command->setStderr($pipes[2]);

        $this->assertTrue(is_resource($process), 'proccess is a resource');
        $this->assertTrue(is_resource($stdin), 'stdin is still a resource');
        $this->assertTrue(is_resource($stdout), 'stdout is a resource');
        $this->assertTrue(is_resource($stderr), 'stderr is a resource');

        $command->finish();

        $this->assertFalse(is_resource($process), 'proccess is not a resource');
        $this->assertFalse(is_resource($stdin), 'stdout is not a resource');
        $this->assertFalse(is_resource($stdout), 'stdout is not a resource');
        $this->assertFalse(is_resource($stderr), 'stderr is not a resource');
    }

    public function testResourcesAreCleanedOnDestruct()
    {
        $command = new Host_Command('head', ['-n1'], 'TikiWiki');
        $pipes = [];
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        $commandLine = $command->getFullCommand();
        $process = proc_open($commandLine, $descriptorspec, $pipes);
        stream_copy_to_stream($command->getStdin(), $pipes[0]);
        fclose($pipes[0]);

        $process = $command->setProcess($process);
        $stdin = $command->getStdin();
        $stdout = $command->setStdout($pipes[1]);
        $stderr = $command->setStderr($pipes[2]);

        $this->assertTrue(is_resource($process), 'proccess is a resource');
        $this->assertTrue(is_resource($stdin), 'stdin is still a resource');
        $this->assertTrue(is_resource($stdout), 'stdout is a resource');
        $this->assertTrue(is_resource($stderr), 'stderr is a resource');

        // $command->__destruct() calls $command->finish();
        unset($command);

        $this->assertFalse(is_resource($process), 'proccess is not a resource');
        $this->assertFalse(is_resource($stdin), 'stdout is not a resource');
        $this->assertFalse(is_resource($stdout), 'stdout is not a resource');
        $this->assertFalse(is_resource($stderr), 'stderr is not a resource');
    }

    public function testRunAlreadyProcessedCommandThrowsError()
    {
        $command = new Host_Command('head', ['-n1'], 'TikiWiki');
        $pipes = [];
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        $commandLine = $command->getFullCommand();
        $process = proc_open($commandLine, $descriptorspec, $pipes);
        stream_copy_to_stream($command->getStdin(), $pipes[0]);
        fclose($pipes[0]);

        $process = $command->setProcess($process);
        $stdin = $command->getStdin();
        $stdout = $command->setStdout($pipes[1]);
        $stderr = $command->setStderr($pipes[2]);

        try {
            $command->run(null);
        } catch (Host_CommandException $e) {
            $this->assertEquals(
                'Host_Command cannot run twice',
                $e->getMessage()
            );
        }
    }
}
