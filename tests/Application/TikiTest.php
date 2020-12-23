<?php

namespace TikiManager\Tests\Application;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use TikiManager\Access\Local;
use TikiManager\Application\Instance;
use TikiManager\Application\Tiki;
use TikiManager\Config\Environment;
use TikiManager\Libs\Host\Command;
use TikiManager\Style\TikiManagerStyle;

class TikiTest extends TestCase
{
    /** @var BufferedOutput */
    protected $output;

    /** @var TikiManagerStyle */
    protected $io;

    public function setUp()
    {
        $input = new ArrayInput([]);
        $this->output = $output = new BufferedOutput();
        Environment::getInstance()->setIO($input, $output);
    }

    /**
     * @covers \TikiManager\Application\Tiki::runComposer
     */
    public function testRunComposerUsingTikiSetupSuccessfully()
    {
        $commandStub = $this->createMock(Command::class);
        $commandStub->method('run')->willReturn(null);
        // Tiki Setup does not return exit codes (return only 0)
        $commandStub->method('getReturn')->willReturn(0);

        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';

        $accessStub = $this->createMock(Local::class);
        $accessStub
            ->method('createCommand')
            ->with('bash', ['setup.sh', 'composer'])
            ->willReturn($commandStub);
        $accessStub
            ->expects($this->atLeastOnce())
            ->method('fileExists')
            ->withConsecutive(
                ['temp/composer.phar'],
                ['vendor_bundled/vendor/autoload.php'],
                ['composer.lock']
            )
            ->will($this->onConsecutiveCalls(
                false, // 'temp/composer.phar'
                true,  //'vendor_bundled/vendor/autoload.php'
                false  // 'composer.lock'
            ));


        $instanceStub->method('hasConsole')->willReturn(true);
        $instanceStub->method('getBestAccess')->willReturn($accessStub);

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['runComposer'])
            ->getMock();

        $tikiStub->runComposer();

        // runComposer is void. If no exception is thrown assumes it is OK
        $this->assertTrue(true);
    }

    /**
     * @covers \TikiManager\Application\Tiki::runComposer
     */
    public function testRunComposerUsingTikiSetupWithErrors()
    {
        $commandStub = $this->createMock(Command::class);
        $commandStub->method('run')->willReturn(null);
        // Tiki Setup does not return exit codes (return only 0)
        $commandStub->method('getReturn')->willReturn(0);

        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';

        $accessStub = $this->createMock(Local::class);
        $accessStub
            ->method('createCommand')
            ->with('bash', ['setup.sh', 'composer'])
            ->willReturn($commandStub);
        $accessStub
            ->expects($this->atLeastOnce())
            ->method('fileExists')
            ->withConsecutive(
                ['temp/composer.phar'],
                ['vendor_bundled/vendor/autoload.php'],
                ['composer.lock']
            )
            ->will($this->onConsecutiveCalls(
                false, // 'temp/composer.phar'
                false,  //'vendor_bundled/vendor/autoload.php'
                false  // 'composer.lock'
            ));


        $instanceStub->method('hasConsole')->willReturn(true);
        $instanceStub->method('getBestAccess')->willReturn($accessStub);

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['runComposer'])
            ->getMock();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp('/^Composer install failed for Tiki bundled packages/');

        $tikiStub->runComposer();
    }

    /**
     * @covers \TikiManager\Application\Tiki::runComposer
     */
    public function testRunComposerUsingComposerSuccessfully()
    {
        $commandStub = $this->createMock(Command::class);
        $commandStub->method('run')->willReturn(null);
        $commandStub->method('getReturn')->willReturn(0);

        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';
        $instanceStub->phpexec = '/usr/bin/php';

        $accessStub = $this->createMock(Local::class);
        $accessStub
            ->method('createCommand')
            ->with($instanceStub->phpexec,
                ['temp/composer.phar', 'install', '-d vendor_bundled', '--no-interaction', '--prefer-dist'])
            ->willReturn($commandStub);
        $accessStub
            ->expects($this->atLeastOnce())
            ->method('fileExists')
            ->withConsecutive(
                ['temp/composer.phar'],
                ['vendor_bundled/vendor/autoload.php'],
                ['composer.lock']
            )
            ->will($this->onConsecutiveCalls(
                true, // 'temp/composer.phar'
                true,  //'vendor_bundled/vendor/autoload.php'
                false  // 'composer.lock'
            ));

        $instanceStub->method('hasConsole')->willReturn(true);
        $instanceStub->method('getBestAccess')->willReturn($accessStub);

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['runComposer'])
            ->getMock();

        $tikiStub->runComposer();

        // runComposer is void. If no exception is thrown assumes it is OK
        $this->assertTrue(true);
    }

    /**
     * @covers \TikiManager\Application\Tiki::runComposer
     */
    public function testRunComposerUsingComposerWithErrors()
    {
        $commandStub = $this->createMock(Command::class);
        $commandStub->method('run')->willReturn(null);
        $commandStub->method('getReturn')->willReturn(1); // Error code different than 0

        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';
        $instanceStub->phpexec = '/usr/bin/php';

        $accessStub = $this->createMock(Local::class);
        $accessStub
            ->method('createCommand')
            ->with($instanceStub->phpexec,
                ['temp/composer.phar', 'install', '-d vendor_bundled', '--no-interaction', '--prefer-dist'])
            ->willReturn($commandStub);
        $accessStub
            ->expects($this->atLeastOnce())
            ->method('fileExists')
            ->withConsecutive(
                ['temp/composer.phar'],
                ['vendor_bundled/vendor/autoload.php'],
                ['composer.lock']
            )
            ->will($this->onConsecutiveCalls(
                true, // 'temp/composer.phar'
                false,  //'vendor_bundled/vendor/autoload.php'
                false  // 'composer.lock'
            ));

        $instanceStub->method('hasConsole')->willReturn(true);
        $instanceStub->method('getBestAccess')->willReturn($accessStub);

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['runComposer'])
            ->getMock();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageRegExp('/^Composer install failed for Tiki bundled packages/');

        $tikiStub->runComposer();
    }


    public function testRunComposerForRootFolderSuccessfully()
    {
        $commandStub = $this->createMock(Command::class);
        $commandStub->method('run')->willReturn(null);
        $commandStub->method('getReturn')->willReturn(0);

        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';
        $instanceStub->phpexec = '/usr/bin/php';

        $accessStub = $this->createMock(Local::class);
        $accessStub
            ->method('createCommand')
            ->withConsecutive(
                [$instanceStub->phpexec,
                    ['temp/composer.phar', 'install', '-d vendor_bundled', '--no-interaction', '--prefer-dist']
                ],
                [$instanceStub->phpexec, ['temp/composer.phar', 'install', '--no-interaction', '--prefer-dist']]
            )
            ->willReturn($commandStub);
        $accessStub
            ->expects($this->atLeastOnce())
            ->method('fileExists')
            ->withConsecutive(
                ['temp/composer.phar'],
                ['vendor_bundled/vendor/autoload.php'],
                ['composer.lock'],
                ['vendor/autoload.php']
            )
            ->will($this->onConsecutiveCalls(
                true, // 'temp/composer.phar'
                true,  //'vendor_bundled/vendor/autoload.php'
                true, // 'composer.lock'
                true  // 'vendor/autoload.php'
            ));

        $instanceStub->method('hasConsole')->willReturn(true);
        $instanceStub->method('getBestAccess')->willReturn($accessStub);

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['runComposer'])
            ->getMock();

        $tikiStub->runComposer();

        // runComposer is void. If no exception is thrown assumes it is OK
        $this->assertTrue(true);
    }

    public function testRunComposerForRootFolderWithErrors()
    {
        $commandStub = $this->createMock(Command::class);
        $commandStub->method('run')->willReturn(null);
        $commandStub
            ->method('getReturn')
            ->will($this->onConsecutiveCalls(
                0, // composer install on vendor_bundled
                1 // composer install on project root folder
            )
            );

        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';
        $instanceStub->phpexec = '/usr/bin/php';

        $accessStub = $this->createMock(Local::class);
        $accessStub
            ->method('createCommand')
            ->withConsecutive(
                [$instanceStub->phpexec,
                    ['temp/composer.phar', 'install', '-d vendor_bundled', '--no-interaction', '--prefer-dist']
                ],
                [$instanceStub->phpexec, ['temp/composer.phar', 'install', '--no-interaction', '--prefer-dist']]
            )
            ->willReturn($commandStub);
        $accessStub
            ->expects($this->atLeastOnce())
            ->method('fileExists')
            ->withConsecutive(
                ['temp/composer.phar'],
                ['vendor_bundled/vendor/autoload.php'],
                ['composer.lock'],
                ['vendor/autoload.php']
            )
            ->will($this->onConsecutiveCalls(
                true, // 'temp/composer.phar'
                true,  //'vendor_bundled/vendor/autoload.php'
                true, // 'composer.lock'
                false  // 'vendor/autoload.php'
            ));

        $instanceStub->method('hasConsole')->willReturn(true);
        $instanceStub->method('getBestAccess')->willReturn($accessStub);

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['runComposer'])
            ->getMock();

        $tikiStub->runComposer();

        // runComposer is void. If no exception is thrown assumes it is OK
        $outputContent = $this->output->fetch();
        $this->assertStringContainsString('[ERROR] Composer install failed for composer.lock in the root folder',
            $outputContent);
    }

}
