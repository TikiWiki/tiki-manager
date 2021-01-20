<?php

namespace TikiManager\Tests\Command\Traits;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use TikiManager\Application\Discovery;
use TikiManager\Application\Instance;
use TikiManager\Application\Tiki;
use TikiManager\Application\Version;
use TikiManager\Command\CreateInstanceCommand;
use TikiManager\Command\Traits\InstanceConfigure;
use TikiManager\Config\App;
use TikiManager\Config\Environment;
use TikiManager\Libs\Database\Database;

/**
 * Class ConfiguratorTest
 * @package TikiManager\Tests\Application\Instance
 * @group unit
 */
class InstanceConfigureTest extends TestCase
{
    /** @var Instance  */
    private $instance;

    private $input;

    private static $inputDefinition;

    private $traitMock;

    public static function setUpBeforeClass()
    {
        $command = new CreateInstanceCommand();
        static::$inputDefinition = $command->getDefinition();
    }

    public function setUp()
    {
        $this->input = new ArrayInput([], static::$inputDefinition);
        $this->input->setInteractive(false);
        $output = new BufferedOutput();
        Environment::getInstance()->setIO($this->input, $output);

        $methods = [
            'installApplication',
            'getApplications',
            'getDiscovery',
            'database',
            'save'
        ];

        $this->instance = $this->getMockBuilder(Instance::class)->setMethods($methods)->getMock();
        $this->instance->type = 'local';

        App::getContainer()->set('instance', $this->instance);

        $this->traitMock = $this->getObjectForTrait(InstanceConfigure::class);
        $this->traitMock->input = $this->input;
        $this->traitMock->io = App::get('io');
        $this->traitMock->setLogger(new NullLogger());
    }

    public function tearDown()
    {
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->remove($_ENV['TESTS_BASE_FOLDER']);
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::setupAccess
     */
    public function testSetupLocalAccess()
    {
        $discovery = $this->createMock(Discovery::class);
        $discovery
            ->method('detectUser')
            ->willReturn('root');

        $this->instance
            ->method('getDiscovery')
            ->willReturn($discovery);

        $this->input->setOption('type', 'local');

        $this->traitMock->setupAccess($this->instance);

        $this->assertEquals('local', $this->instance->type);
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::setupInstance
     */
    public function testSetupInstance()
    {
        $basePath = $_ENV['TESTS_BASE_FOLDER'];
        $instanceWebRoot = $basePath . '/instance-test-' . md5(random_bytes(5));
        $instanceTempDir = $basePath . '/temp';

        $this->input->setOption('url', 'http://test.tiki.local');
        $this->input->setOption('email', 'test@example.local');
        $this->input->setOption('webroot', $instanceWebRoot);
        $this->input->setOption('tempdir', $instanceTempDir);
        // Backup information is calculated

        $methods = ['save'];
        $instance = $this->getMockBuilder(Instance::class)->setMethods($methods)->getMock();
        $instance->type = 'local';

        $this->traitMock->setupInstance($instance);

        $this->assertEquals('http://test.tiki.local', $instance->weburl);
        $this->assertEquals('test.tiki.local', $instance->name);
        $this->assertEquals('test@example.local', $instance->contact);

        $this->assertEquals($instanceWebRoot, $instance->webroot);
        $this->assertEquals($instanceTempDir, $instance->tempdir);
        $this->assertFileExists($instanceWebRoot);
        $this->assertFileExists($instanceTempDir);

        $backupDir = Environment::get('BACKUP_FOLDER');
        $defaultBackupUser = posix_getpwuid(fileowner($backupDir));
        $defaultBackupGroup = posix_getgrgid(filegroup($backupDir));
        $defaultBackupPerm = sprintf('%o', fileperms($backupDir) & 0777);

        $this->assertEquals($defaultBackupUser['name'], $instance->backup_user);
        $this->assertEquals($defaultBackupGroup['name'], $instance->backup_group);
        $this->assertEquals(octdec($defaultBackupPerm), $instance->backup_perm);
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::setupApplication
     */
    public function testSetupBlankApplication()
    {
        $this->input->setOption('blank', true);
        $this->traitMock->setupApplication($this->instance);
        $this->assertEquals( 'blank : none', $this->instance->selection);
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::setupApplication
     */
    public function testSetupApplication()
    {
        $this->input->setOption('branch', 'master');

        $versions = [
            Version::buildFake('git', '21.x'),
            Version::buildFake('git', 'master'),
        ];

        $instance = $this->getMockBuilder(Instance::class)
            ->setMethods(['getCompatibleVersions'])
            ->getMock();
        $instance
            ->expects($this->once())
            ->method('getCompatibleVersions')
            ->willReturn($versions);

        $instance->type = 'local';

        $this->traitMock->setupApplication($instance);

        $this->assertEquals($instance->selection, 'git : master');
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::setupApplication
     */
    public function testSetupApplicationInvalidBranch()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Selected branch not found.');

        $this->input->setOption('branch', 'dummy');

        $versions = [
            Version::buildFake('git', '21.x'),
            Version::buildFake('git', 'master'),
        ];

        $instance = $this->getMockBuilder(Instance::class)
            ->setMethods(['getCompatibleVersions'])
            ->getMock();
        $instance
            ->expects($this->once())
            ->method('getCompatibleVersions')
            ->willReturn($versions);

        $instance->type = 'local';

        $this->traitMock->setupApplication($instance);
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::setupDatabase
     */
    public function testSetupDatabaseInstanceInvalidAdministrator()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unable to access database');

        $this->input->setOption('db-host', 'localhost');
        $this->input->setOption('db-user', 'invalid');
        $this->input->setOption('db-pass', 'invalidSecret');

        $instance = new Instance();
        $instance->type = 'local';

        $this->traitMock->setupDatabase($instance);
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::setupDatabase
     */
    public function testSetupDatabaseWithPrefix()
    {
        $prefix = substr(md5(random_bytes(5)), 0, 8);
        $this->input->setOption('db-prefix', $prefix);

        $instance = new Instance();
        $instance->type = 'local';

        $this->traitMock->setupDatabase($instance);
        $result = $instance->getDatabaseConfig();

        $expected = [
            'host' => Environment::get('DB_HOST') ?? 'localhost',
            'user' => Environment::get('DB_USER') ?? 'root',
            'pass' => Environment::get('DB_PASS') ?? '',
            'database' => null,
            'prefix' => $prefix
        ];

        foreach ($expected as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $result[$key]);
        }
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::setupDatabase
     */
    public function testSetupDatabaseWithDatabaseName()
    {
        $dbName = substr(md5(random_bytes(5)), 0, 8);
        $this->input->setOption('db-name', $dbName);

        $instance = new Instance();
        $instance->type = 'local';

        $this->traitMock->setupDatabase($instance);
        $result = $instance->getDatabaseConfig();

        $this->assertIsArray($result);

        $expected = [
            'host' => Environment::get('DB_HOST') ?? 'localhost',
            'user' => Environment::get('DB_USER') ?? 'root',
            'pass' => Environment::get('DB_PASS') ?? null,
            'database' => $dbName,
            'prefix' => null
        ];

        foreach ($expected as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $result[$key]);
        }
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::setupDatabase
     */
    public function testSetupDatabaseWithDefaults()
    {
        $databaseStub = $this->createMock(Database::class);

        $databaseStub->expects($this->once())
            ->method('testConnection')
            ->willReturn(true);

        $databaseStub->expects($this->once())
            ->method('hasCreateUserPermissions')
            ->willReturn(true);

        $databaseStub->expects($this->once())
            ->method('hasCreateDatabasePermissions')
            ->willReturn(true);

        $databaseStub->expects($this->once())
            ->method('getMaxUsernameLength')
            ->willReturn(11);

        $databaseStub->expects($this->once())
            ->method('userExists')
            ->willReturn(false);

        $databaseStub->expects($this->once())
            ->method('databaseExists')
            ->willReturn(false);

        $this->instance->method('database')->willReturn($databaseStub);

        $this->traitMock->setupDatabase($this->instance);
        $result = $this->instance->getDatabaseConfig();

        $this->assertIsArray($result);

        $expected = [
            'host' => Environment::get('DB_HOST') ?? 'localhost',
            'user' => Environment::get('DB_USER') ?? 'root',
            'pass' => Environment::get('DB_PASS') ?? null,
            'database' => null,
            'prefix' => 'tiki'
        ];

        foreach ($expected as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $result[$key]);
        }
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::setupDatabase
     */
    public function testSetupDatabaseWithExistingUser()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("User 'tiki_user' already exists, can't proceed.");

        $this->instance->type = 'local';

        $databaseStub = $this->createMock(Database::class);

        $databaseStub->expects($this->once())
            ->method('testConnection')
            ->willReturn(true);

        $databaseStub->expects($this->once())
            ->method('hasCreateUserPermissions')
            ->willReturn(true);

        $databaseStub->expects($this->once())
            ->method('hasCreateDatabasePermissions')
            ->willReturn(true);

        $databaseStub->expects($this->once())
            ->method('getMaxUsernameLength')
            ->willReturn(11);

        $databaseStub->expects($this->once())
            ->method('userExists')
            ->willReturn(true);

        $this->instance->method('database')->willReturn($databaseStub);

        $this->traitMock->setupDatabase($this->instance);
    }

    /**
     * @covers \TikiManager\Application\Instance\Configurator::install
     */
    public function testInstall()
    {
        $discovery = $this->getMockBuilder(Discovery::class)
            ->setConstructorArgs([$this->instance])
            ->setMethods(['detectVcsType', 'detectPHP', 'detectPHPVersion'])
            ->getMock();

        $discovery
            ->method('detectVcsType')
            ->willReturn('GIT');

        $discovery
            ->method('detectPHP')
            ->willReturn(PHP_BINARY);

        $discovery
            ->method('detectPHPVersion')
            ->willReturn(intval(PHP_VERSION_ID, 10));

        $this->instance
            ->method('save')
            ->willReturn(null);

        $this->instance
            ->method('getDiscovery')
            ->willReturn($discovery);

        $application = $this->getMockBuilder(Tiki::class)
            ->setConstructorArgs([$this->instance])
            ->setMethods(['isInstalled', 'registerCurrentInstallation'])
            ->getMock();

        $application
            ->method('isInstalled')
            ->willReturn(false);

        $application
            ->method('registerCurrentInstallation')
            ->willReturn($this->instance);

        $this->instance
            ->method('getApplications')
            ->willReturn([$application]);

        $this->instance
            ->method('installApplication')
            ->willReturn(true);

        $this->instance->selection = 'git : master';

        $this->traitMock->install($this->instance);

        $this->assertEquals('GIT', $this->instance->vcs_type);
        $this->assertEquals(PHP_BINARY, $this->instance->phpexec);
        $this->assertEquals(intval(PHP_VERSION_ID, 10), $this->instance->phpversion);
    }
}
