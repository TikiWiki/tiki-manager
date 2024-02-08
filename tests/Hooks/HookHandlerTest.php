<?php

namespace TikiManager\Tests\Hooks;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use TikiManager\Hooks\HookHandler;
use TikiManager\Hooks\InstanceUpgradeHook;
use TikiManager\Hooks\TikiCommandHook;

/**
 * @group unit
 */
class HookHandlerTest extends TestCase
{

    public function testHookHandlerValidHooksFolder()
    {
        $vsfStream = vfsStream::setup('exampleDir');
        $hookHandler= new HookHandler($vsfStream->url());

        $this->assertInstanceOf(HookHandler::class, $hookHandler);
    }

    public function testHookHandlerInvalidHooksFolder()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Hooks folder does not exist.');

        $vsfStream = vfsStream::setup('exampleDir');
        new HookHandler($vsfStream->url() . '/invalid');
    }

    public function testGetHookWithExtendedClass()
    {
        $hookHandler = new HookHandler($_ENV['HOOKS_FOLDER']);
        $hook = $hookHandler->getHook('instance:upgrade');
        $this->assertInstanceOf(InstanceUpgradeHook::class, $hook);
    }

    public function testGetHookBaseClass()
    {
        $hookHandler = new HookHandler($_ENV['HOOKS_FOLDER']);
        $hook = $hookHandler->getHook('instance:dummy');

        $this->assertInstanceOf(TikiCommandHook::class, $hook);
    }
}
