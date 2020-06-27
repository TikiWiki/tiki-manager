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
    static $testPath;


    public static function setUpBeforeClass()
    {
        static::$testPath = Environment::get('TEMP_FOLDER') . '/test-update-manager';
    }

    protected function setUp()
    {
        $fs = new Filesystem();
        $fs->mkdir(static::$testPath);
    }

    protected function tearDown()
    {
        $fs = new Filesystem();
        $fs->remove(static::$testPath);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Unable to install composer dependencies
     */
    public function testRunComposerInstallShouldFail()
    {
        $updater = UpdateManager::getUpdater(static::$testPath);
        Tests::invokeMethod($updater, 'runComposerInstall');
    }

    public function testRunComposerInstall()
    {
        $fs = new Filesystem();

        $json = <<<JSON
{
    "require": {
        "monolog/monolog": "1.0.*"
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
