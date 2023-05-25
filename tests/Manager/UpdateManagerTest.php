<?php

namespace TikiManager\Tests\Manager\Update;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Config\Environment;
use TikiManager\Manager\UpdateManager;
use TikiManager\Tests\Helpers\Tests;

/**
 * Class UpdateManagerTest
 * @group unit
 */
class UpdateManagerTest extends TestCase
{
    /**
     * @var string
     */
    protected static $testPath;

    public static function setUpBeforeClass(): void
    {
        static::$testPath = Environment::get('TEMP_FOLDER') . '/test-update-manager';
    }

    protected function setUp(): void
    {
        $fs = new Filesystem();
        $fs->mkdir(static::$testPath);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove(static::$testPath);
    }

    public function testRunComposerInstallShouldFail()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unable to install composer dependencies');

        $updater = UpdateManager::getUpdater(static::$testPath);
        Tests::invokeMethod($updater, 'runComposerInstall');
    }

    public function testRunComposerInstall()
    {
        $fs = new Filesystem();

        $json = <<<JSON
{
    "require": {
        "symfony/dotenv": "^4.3"
    }
}
JSON;

        $fs->appendToFile(static::$testPath . '/composer.json', $json);

        $updater = UpdateManager::getUpdater(static::$testPath);
        Tests::invokeMethod($updater, 'runComposerInstall');

        $this->assertTrue($fs->exists(static::$testPath . '/composer.lock'));
        $this->assertTrue($fs->exists(static::$testPath . '/vendor'));
    }
}
