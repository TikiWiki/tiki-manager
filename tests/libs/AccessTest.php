<?php
use PHPUnit\Framework\TestCase;

class AccessLocalTest extends TestCase
{
    public function getAccessInstance()
    {
        $instance = $this->createMock(Instance::class);
        $access = new Access_Local($instance);
        return $access;
    }

    public function testCreateCommand()
    {
        $access = $this->getAccessInstance();
        $command = $access->createCommand('cat', [], 'Hello World');

        $command->run();
        $output = $command->getStdoutContent();

        $this->assertEquals('Hello World', $output);
    }

    public function testCreateCommandWithEnv()
    {
        $access = $this->getAccessInstance();
        $access->setenv('FOO', 'bar');

        $command = $access->createCommand('bash', [], 'echo -n $FOO');

        $command->run();
        $output = $command->getStdoutContent();

        $this->assertEquals('bar', $output);
    }
}
