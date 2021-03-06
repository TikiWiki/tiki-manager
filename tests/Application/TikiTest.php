<?php

namespace TikiManager\Tests\Application;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use TikiManager\Access\Local;
use TikiManager\Application\Exception\VcsException;
use TikiManager\Application\Instance;
use TikiManager\Application\Tiki;
use TikiManager\Application\Tiki\Versions\Fetcher\RequirementsFetcher;
use TikiManager\Application\Tiki\Versions\SoftwareRequirement;
use TikiManager\Application\Tiki\Versions\TikiRequirements;
use TikiManager\Application\Tiki\Versions\TikiRequirementsHelper;
use TikiManager\Application\Version;
use TikiManager\Config\Environment;
use TikiManager\Libs\Host\Command;
use TikiManager\Libs\VersionControl\Git;
use TikiManager\Libs\VersionControl\VersionControlSystem;
use TikiManager\Style\TikiManagerStyle;

/**
 * Class TikiTest
 * @package TikiManager\Tests\Application
 * @group unit
 */
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
     * @covers \TikiManager\Application\Tiki::extractTo
     */
    public function testExtractToFailedToUpdate() {

        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';

        $vcsStub = $this->createMock(VersionControlSystem::class);
        $vcsStub
            ->expects($this->once())
            ->method('pull')
            ->willThrowException(new VcsException('error'));

        $instanceStub->vcs_type = 'git';
        $instanceStub
            ->method('getVersionControlSystem')
            ->willReturn($vcsStub);

        $vcsStub
            ->expects($this->once())
            ->method('clone')
            ->willReturn(null);

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['extractTo'])
            ->getMock();

        $version = Version::buildFake('git', 'master');
        $vfsStream = vfsStream::setup('cache');
        $vfsStream->addChild(new vfsStreamDirectory('tiki-git-master'));

        $tikiStub->extractTo($version, $vfsStream->getChild('tiki-git-master')->url());

        // Folder is removed when pull fails
        $this->assertFalse($vfsStream->hasChild('tiki-git-master'));
    }

    /**
     * @covers \TikiManager\Application\Tiki::extractTo
     */
    public function testExtractToFolderDoesNotExist() {

        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';

        $vcsStub = $this->createMock(VersionControlSystem::class);
        $vcsStub
            ->expects($this->never())
            ->method('pull');

        $instanceStub->vcs_type = 'git';
        $instanceStub
            ->method('getVersionControlSystem')
            ->willReturn($vcsStub);

        $vcsStub
            ->expects($this->once())
            ->method('clone')
            ->willReturn(null);

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['extractTo'])
            ->getMock();

        $version = Version::buildFake('git', 'master');
        $vfsStream = vfsStream::setup('cache');

        $tikiStub->extractTo($version, $vfsStream->url() . '/tiki-git-master');
    }

    /**
     * @covers \TikiManager\Application\Tiki::extractTo
     */
    public function testExtractToFolderExist() {

        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';

        $vcsStub = $this->createMock(VersionControlSystem::class);
        $vcsStub
            ->expects($this->once())
            ->method('pull');

        $instanceStub->vcs_type = 'git';
        $instanceStub
            ->method('getVersionControlSystem')
            ->willReturn($vcsStub);

        $vcsStub
            ->expects($this->never())
            ->method('clone')
            ->willReturn(null);

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['extractTo'])
            ->getMock();

        $version = Version::buildFake('git', 'master');
        $vfsStream = vfsStream::setup('cache');
        $vfsStream->addChild(new vfsStreamDirectory('tiki-git-master'));

        $tikiStub->extractTo($version, $vfsStream->getChild('tiki-git-master')->url());
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
                ['temp/composer.phar', 'install', '-d vendor_bundled', '--no-interaction', '--prefer-dist' , '--no-dev'])
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
                ['temp/composer.phar', 'install', '-d vendor_bundled', '--no-interaction', '--prefer-dist', '--no-dev'])
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
                    ['temp/composer.phar', 'install', '-d vendor_bundled', '--no-interaction', '--prefer-dist', '--no-dev']
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
                    ['temp/composer.phar', 'install', '-d vendor_bundled', '--no-interaction', '--prefer-dist', '--no-dev']
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

    /**
     * @covers \TikiManager\Application\Tiki::postInstall
     */
    public function testPostInstallWithSkipReindex()
    {
        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';
        $instanceStub->vcs_type = 'git';
        $instanceStub->method('getVersionControlSystem')->willReturn(new Git($instanceStub));

        $instanceStub
            ->expects($this->once())
            ->method('hasConsole')
            ->willReturn(true);

        $accessStub = $this->createMock(Local::class);
        $accessStub
            ->expects($this->once())
            ->method('shellExec')
            ->willReturn(null);

        $instanceStub
            ->method('getBestAccess')
            ->willReturn($accessStub);

        $tikiMock = $this->createPartialMock(
            Tiki::class,
            ['runComposer', 'runDatabaseUpdate', 'setDbLock', 'clearCache', 'fixPermissions']
        );

        $tikiMock->__construct($instanceStub);

        $tikiMock
            ->expects($this->once())
            ->method('runDatabaseUpdate');

        $tikiMock
            ->expects($this->once())
            ->method('setDbLock');

        $tikiMock
            ->expects($this->once())
            ->method('fixPermissions');

        $tikiMock->postInstall(['skip-reindex' => true]);
    }

    /**
     * @covers \TikiManager\Application\Tiki::getCompatibleVersions
     */
    public function testGetCompatibleVersions()
    {
        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->vcs_type = 'git';

        $vcsStub = $this->createMock(VersionControlSystem::class);
        $instanceStub
            ->method('getVersionControlSystem')
            ->willReturn($vcsStub);

        $fetcher = $this->createMock(RequirementsFetcher::class);
        $fetcher->method('getRequirements')->willReturn(
            array_map(function($req){
                    return new TikiRequirements(
                        $req['name'],
                        $req['version'],
                        new SoftwareRequirement($req['php']['min'] ?? '', $req['php']['max'] ?? ''),
                        new SoftwareRequirement($req['mysql']['min'] ?? '', $req['mysql']['max'] ?? ''),
                        new SoftwareRequirement($req['mariadb']['min'] ?? '', $req['mariadb']['max'] ?? ''));
            },[
                0 => [
                    'name' => 'Tiki22',
                    'version' => 22,
                    'php' => [
                        'min' => '7.4.0',
                    ],
                    'mysql' => [
                        'min' => '5.5.0',
                    ],
                    'mariadb' => [
                        'min' => '5.7.0',
                    ],
                ],
                1 => [
                    'name' => 'Tiki19',
                    'version' => 19,
                    'php' => [
                        'min' => '7.1.0',
                        'max' => '7.2.0',
                    ],
                    'mysql' => [
                        'min' => '5.5.0',
                        'max' => '10.4.0',
                    ],
                    'mariadb' => [
                        'min' => '5.5.3',
                        'max' => '5.7.0',
                    ],
                ],
                2 => [
                    'name' => 'Tiki12 LTS',
                    'version' => 12,
                    'php' => [
                        'min' => '5.3.0',
                        'max' => '5.6.0',
                    ],
                    'mysql' => [
                        'min' => '5.1.0',
                        'max' => '5.5.0',
                    ],
                    'mariadb' => [
                        'min' => '5.0.0',
                        'max' => '5.5.0',
                    ],
                ],
            ])
        );

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['getCompatibleVersions'])
            ->getMock();

        $tikiStub->method('getTikiRequirementsHelper')->willReturn(
            new TikiRequirementsHelper($fetcher)
        );

        $tikiStub->method('getVersions')->willReturn(
            [
                (object)[
                    'type' => 'git',
                    'branch' => '11.x',
                    'date' => '2021-02-20',
                ],
                (object)[
                    'type' => 'git',
                    'branch' => '20.x',
                    'date' => '2021-02-20',
                ],
                (object)[
                    'type' => 'git',
                    'branch' => '22.x',
                    'date' => '2021-02-20',
                ],
                (object)[
                    'type' => 'git',
                    'branch' => 'tags/12.0RC4',
                    'date' => '2021-02-20',
                ],
                (object)[
                    'type' => 'git',
                    'branch' => 'tags/19.0beta1',
                    'date' => '2021-02-20',
                ],
                (object)[
                    'type' => 'git',
                    'branch' => 'tags/22.1^{}',
                    'date' => '2021-02-20',
                ],
                (object)[
                    'type' => 'git',
                    'branch' => 'tags/26.1^{}',
                    'date' => '2021-02-20',
                ],
                (object)[
                    'type' => 'git',
                    'branch' => 'master',
                    'date' => '2021-02-20',
                ],
                (object)[
                    'type' => 'git',
                    'branch' => 'trunk',
                    'date' => '2021-02-20',
                ],
            ]
        );
        $instanceStub->phpversion = 70415;

        $compatible = $tikiStub->getCompatibleVersions();
        $branches = array_map(function ($version) {
            return is_object($version) ? $version->branch : $version;
        }, $compatible);

        $this->assertContains("tags/22.1^{}", $branches);
        $this->assertContains("22.x", $branches);
        $this->assertContains("tags/26.1^{}", $branches);
        $this->assertContains("master", $branches);
        $this->assertContains("trunk", $branches);
        $this->assertCount(6, $branches);

        $instanceStub->phpversion = 70200;

        $compatible = $tikiStub->getCompatibleVersions();
        $branches = array_map(function ($version) {
            return is_object($version) ? $version->branch : $version;
        }, $compatible);

        $this->assertContains("20.x", $branches);
        $this->assertContains("tags/19.0beta1", $branches);
        $this->assertCount(3, $branches);

        $instanceStub->phpversion = 50600;

        $compatible = $tikiStub->getCompatibleVersions();
        $branches = array_map(function ($version) {
            return is_object($version) ? $version->branch : $version;
        }, $compatible);

        $this->assertContains("11.x", $branches);
        $this->assertContains("tags/12.0RC4", $branches);
        $this->assertCount(3, $branches);
    }

    /**
     * @covers Tiki::setPref
     */
    public function testSetPref()
    {
        $instanceStub = $this->createMock(Instance::class);
        $instanceStub->type = 'local';
        $instanceStub->phpexec = '/usr/bin/php';

        $tikiStub = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$instanceStub])
            ->setMethodsExcept(['setPref'])
            ->getMock();

        $commandStub = $this->createMock(Command::class);
        $commandStub
            ->expects($this->once())
            ->method('getReturn')
            ->willReturn(0);

        $commandStub
            ->expects($this->once())
            ->method('getStdoutContent')
            ->willReturn('Preference tmpDir was set.');

        $accessStub = $this->createMock(Local::class);
        $accessStub
            ->method('createCommand')
            ->with('/usr/bin/php', ['-q', 'console.php', 'preferences:set', 'tmpDir', '/tmp/random'])
            ->willReturn($commandStub);

        $instanceStub->method('hasConsole')->willReturn(true);
        $instanceStub->method('getBestAccess')->willReturn($accessStub);

        $this->assertTrue($tikiStub->setPref('tmpDir', '/tmp/random'));
    }
}
