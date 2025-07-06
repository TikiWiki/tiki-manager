<?php

namespace TikiManager\Tests\Hooks;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;
use TikiManager\Config\App;
use TikiManager\Hooks\HookHandler;
use TikiManager\Hooks\TikiCommandHook;

/**
 * @group unit
 */
class TikiCommandHookTest extends TestCase
{
    public function testGetPath()
    {
        $hook = new TikiCommandHook('instance-create', new NullLogger());

        $_ENV['HOOKS_FOLDER'] = __DIR__ . '/hooks';

        $expected = App::get('HookHandler')->getHooksFolder() . '/instance-create';
        $this->assertEquals($expected, $hook->getPath());
    }

    public function testGetScripts()
    {
        $container = App::getContainer();

        $files = [
            'sound.sh' => 'play a sound',
            'notify.sh' => 'notify the channel',
        ];

        $structure = [
            'hooks' => [
                'instance-create' => [
                    'post' => $files,
                ],
            ]
        ];

        $fileSystem = vfsStream::create($structure, vfsStream::setup());

        $hookHandler = new HookHandler($fileSystem->url() . '/hooks');
        $container->set('HookHandler', $hookHandler);

        $hook = new TikiCommandHook('instance-create', new NullLogger());
        $scripts = $hook->getScripts('post');

        $this->assertCount(2, $scripts);

        foreach ($scripts as $script) {
            $filename = $script->getFileName();
            $this->assertTrue(array_key_exists($filename, $files), 'File ' . $filename . ' is not expected');
        }
    }

    public function testGetScriptsFolderDoesNotExist()
    {
        $container = App::getContainer();

        $fileSystem = vfsStream::create(['hooks' => []], vfsStream::setup());

        $hookHandler = new HookHandler($fileSystem->url() . '/hooks');
        $container->set('HookHandler', $hookHandler);

        $testHandler = new TestHandler();
        $logger = new Logger('test', [$testHandler]);

        $hook = new TikiCommandHook('instance-create', $logger);
        $scripts = $hook->getScripts('post');

        $this->assertNull($scripts);
        $this->assertTrue($testHandler->hasDebugThatContains('directory does not exist'));
    }

    /**
     * @covers \TikiManager\Hooks\TikiCommandHook::runScriptCommand
     * @return void
     */
    public function testRunScriptCommandWithFailure()
    {
        $testHandler = new TestHandler();
        $logger = new Logger('test', [$testHandler]);
        $hook = new TikiCommandHook('instance-create', $logger);
        $processMock = $this->createMock(Process::class);

        $processMock
            ->expects($this->once())
            ->method('getCommandLine')
            ->willReturn('bash test.sh');

        $processMock
            ->expects($this->once())
            ->method('getWorkingDirectory')
            ->willReturn('/tmp');

        $processMock
            ->expects($this->once())
            ->method('getEnv')
            ->willReturn($_ENV);

        $processMock
            ->expects($this->once())
            ->method('run');

        $processMock
            ->expects($this->once())
            ->method('getExitCode')
            ->willReturn(1);

        $processMock
            ->expects($this->once())
            ->method('getErrorOutput')
            ->willReturn('Error test!');

        $this->assertFalse($this->invokeMethod($hook, 'runScriptCommand', [$processMock]));

        $Loglevel = class_exists('Monolog\Level') ? Level::Debug : $logger::DEBUG;
        $this->assertTrue($testHandler->hasRecord([
            'message' => 'Command {command}',
            'context' => [
                'command' => 'bash test.sh',
                'cwd' => '/tmp',
                'env' => $_ENV
            ]
        ], $Loglevel));

        $this->assertTrue($testHandler->hasError('Error test!'));
    }

    /**
     * @covers \TikiManager\Hooks\TikiCommandHook::runScriptCommand
     * @return void
     */
    public function testRunScriptCommandWithSuccess()
    {
        $testHandler = new TestHandler();
        $logger = new Logger('test', [$testHandler]);
        $hook = new TikiCommandHook('instance-create', $logger);
        $processMock = $this->createMock(Process::class);
        $processMock
            ->expects($this->once())
            ->method('run');

        $processMock
            ->expects($this->once())
            ->method('getExitCode')
            ->willReturn(0);

        $processMock
            ->expects($this->once())
            ->method('getOutput')
            ->willReturn('Completed!');

        $processMock
            ->expects($this->never())
            ->method('getErrorOutput');

        $this->assertTrue($this->invokeMethod($hook, 'runScriptCommand', [$processMock]));

        $this->assertTrue($testHandler->hasDebugThatContains('Completed!'));
    }

    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function testExecuteNoScripts()
    {
        $mock = $this->getMockBuilder(TikiCommandHook::class)
            ->setConstructorArgs(['instance-create', new NullLogger()])
            ->setMethods(['getScripts'])
            ->getMock();

        $mock->expects($this->exactly(2))
            ->method('getScripts')
            ->withConsecutive(['pre', false], ['pre', true])
            ->willReturnOnConsecutiveCalls(null, null);

        $mock->execute('pre');
    }

    public function testExecute()
    {
        $structure = [
            'hooks' => [
                'instance-create' => [
                    'post' => [
                        'sound.sh' => 'play a sound',
                        'notify.sh' => 'notify the channel',
                    ],
                ],
            ]
        ];

        $fileSystem = vfsStream::create($structure, vfsStream::setup());

        $hookHandler = new HookHandler($fileSystem->url() . '/hooks');

        App::getContainer()->set('HookHandler', $hookHandler);

        $processMock = $this->createMock(Process::class);

        $mock = $this->getMockBuilder(TikiCommandHook::class)
            ->setConstructorArgs(['instance-create', new NullLogger()])
            ->setMethods(['buildScriptCommand', 'runScriptCommand'])
            ->getMock();

        $testHandler = new TestHandler();
        $logger = new Logger('test', [$testHandler]);
        $mock->setLogger($logger);

        $mock
            ->method('buildScriptCommand')
            ->willReturn($processMock);

        $mock
            ->method('runScriptCommand')
            ->with($processMock)
            ->willReturnOnConsecutiveCalls(true, false);

        $mock->execute('post');

        $Loglevel = class_exists('Monolog\Level') ? Level::Info : $logger::INFO;
        $path = $fileSystem->url() . '/hooks/instance-create/post/';
        $this->assertTrue($testHandler->hasInfoThatContains('Hook file: ' . $path . 'sound.sh'));
        $this->assertTrue(
            $testHandler->hasRecord(
                [
                    'message' => 'Hook script {status}',
                    'context' => ['status' => 'succeeded']
                ],
                $Loglevel
            )
        );
        $this->assertTrue($testHandler->hasInfoThatContains('Hook file: ' . $path . 'notify.sh'));

        $this->assertTrue(
            $testHandler->hasRecord(
                [
                    'message' => 'Hook script {status}',
                    'context' => ['status' => 'failed']
                ],
                $Loglevel
            )
        );

        $testHandler = new TestHandler();
        $logger = new Logger('test', [$testHandler]);
        $mock->setLogger($logger);

        $mock->execute('pre');
        $this->assertFalse($testHandler->hasInfoThatContains('Hook file'));
    }
}
