<?php

namespace TikiManager\Tests\Access;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use PHPUnit\Framework\TestCase;
use TikiManager\Access\Local;

/**
 * Class LocalTest
 * @package TikiManager\Tests\Access
 * @group unit
 */
class LocalTest extends TestCase
{
    /**
     * @covers \TikiManager\Access\Local::isEmptyDir
     */
    public function testIsEmptyDir()
    {
        $vsfStream = vfsStream::setup('exampleDir');

        $stub = $this->createPartialMock(Local::class, []);
        $output = $stub->isEmptyDir($vsfStream->url());

        $this->assertTrue($output);
    }

    /**
     * @covers \TikiManager\Access\Local::isEmptyDir
     */
    public function testIsNotEmptyDir()
    {

        $vsfStream = vfsStream::setup('exampleDir');
        $vsfStream->addChild(new vfsStreamDirectory('demo'));
        $vsfStream->addChild(new vfsStreamFile('index.php'));

        $stub = $this->createPartialMock(Local::class, []);
        $output = $stub->isEmptyDir($vsfStream->url());

        $this->assertFalse($output);
    }
}
