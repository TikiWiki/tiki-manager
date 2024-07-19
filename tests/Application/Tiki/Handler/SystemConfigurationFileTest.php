<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Application\Tiki\Handler;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use TikiManager\Application\Tiki\Handler\SystemConfigurationFile;
use PHPUnit\Framework\TestCase;

/**
 * @package TikiManager\Tests\Application
 * @group unit
 */

class SystemConfigurationFileTest extends TestCase
{
    protected static array $testDirectives = [
        'xx-non-existing-directive-for-testing-xx',
        'preference.unified_elastic_index_prefix',
        'yy-non-existing-directive-for-testing-yy',
    ];

    protected static array $testDirectivesAlternative = [
        'zz-non-existing-directive-for-testing-zz',
        'preference.unified_mysql_index_current',
        'ww-non-existing-directive-for-testing-ww',
    ];

    protected static vfsStreamDirectory $fileSystem;

    protected static SystemConfigurationFile $basicSystemConfigurationFileHandler;

    protected static ?string $saveEnvironment = null;

    public static function setUpBeforeClass(): void
    {
        // define my virtual file system
        $directory = [
            'ini' => [
                'safe.ini' => '
[global]
preference.feature_wysiwyg = "n"
preference.feature_sefurl = "y"
preference.helpurl = "http://support.example.com/"
; ... more settings ...

[basic : global]
; ... this hierarchical block no longer works from Tiki 15 onwards
preference.feature_wiki = "y"
preference.feature_forums = "n"
preference.feature_trackers = "n"
; ... more settings ...

[pro : global]
; ... this hierarchical block no longer works from Tiki 15 onwards
preference.feature_wiki = "y"
; BBB configured, but user can still toggle on/off
preference.bigbluebutton_server_location = "bbb.example.com"
preference.bigbluebutton_server_salt = "1234abcd1234abcd"
; ... more settings ...

[client1 : pro]
; ... this hierarchical block no longer works from Tiki 15 onwards, but [client1] will
preference.browsertitle = "Client #1 Intranet"
preference.sender_email = client1@example.com
                ',
                'dangerous.ini' => '
[global]
preference.feature_wysiwyg = "n"
preference.feature_sefurl = "y"
preference.helpurl = "http://support.example.com/"
; ... more settings ...

[basic : global]
; ... this hierarchical block no longer works from Tiki 15 onwards
preference.feature_wiki = "y"
preference.unified_elastic_index_prefix = "danger"

[basic2]
preference.feature_wiki = "y"
preference.unified_elastic_index_prefix = "danger"
                ',
                'commented.ini' => '
[global]
preference.feature_wysiwyg = "n"
preference.feature_sefurl = "y"
preference.helpurl = "http://support.example.com/"
; ... more settings ...

[basic : global]
; ... this hierarchical block no longer works from Tiki 15 onwards
preference.feature_wiki = "y"
; preference.unified_elastic_index_prefix = "danger"

[basic2]
preference.feature_wiki = "y"
; preference.unified_elastic_index_prefix = "danger"
                ',
                'dangerous.ini.php' => '
<?php
// This script may only be included - so it is better to die if called directly.
// Keep this block to avoid the content to be read from the internet.
if (strpos($_SERVER[\'SCRIPT_NAME\'], basename(__FILE__)) !== false) {
	header(\'location: index.php\');
	exit;
}
?>
[global]
preference.feature_wysiwyg = "n"
preference.feature_sefurl = "y"
preference.helpurl = "http://support.example.com/"
; ... more settings ...

[basic : global]
; ... this hierarchical block no longer works from Tiki 15 onwards
preference.feature_wiki = "y"
preference.unified_elastic_index_prefix = "danger"

[basic2]
preference.feature_wiki = "y"
preference.unified_elastic_index_prefix = "danger"
                ',
            ]
        ];
        // setup and cache the virtual file system
        self::$fileSystem = vfsStream::setup('SystemConfigurationFileTest', null, $directory);

        self::$saveEnvironment = null;
        if (! empty($_ENV[SystemConfigurationFile::ENV_KEY])) {
            self::$saveEnvironment = $_ENV[SystemConfigurationFile::ENV_KEY];
            unset($_ENV[SystemConfigurationFile::ENV_KEY]);
        }

        self::$basicSystemConfigurationFileHandler = new SystemConfigurationFile();
        self::$basicSystemConfigurationFileHandler->setDangerousDirectives(self::$testDirectives);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$saveEnvironment !== null) {
            $_ENV[SystemConfigurationFile::ENV_KEY] = self::$saveEnvironment;
            self::$saveEnvironment = null;
        }
    }

    public function testGetSetDangerousDirectives()
    {
        if (isset($_ENV[SystemConfigurationFile::ENV_KEY])) {
            unset($_ENV[SystemConfigurationFile::ENV_KEY]);
        }

        // the default list may change over time, so no attempt to guess that
        $obj = new SystemConfigurationFile();
        $defaultList = $obj->getDefaultDangerousDirectives();

        // test setting by calling the set function
        $obj = new SystemConfigurationFile();
        $obj->setDangerousDirectives(self::$testDirectives);
        $directives = $obj->getDangerousDirectives();
        $this->assertEquals(self::$testDirectives, $directives);

        // make sure we do not keep state
        $obj = new SystemConfigurationFile(); // new object
        $this->assertEquals($defaultList, $obj->getDangerousDirectives());

        // make sure we can override using constructor
        $obj = new SystemConfigurationFile(self::$testDirectives);
        $directives = $obj->getDangerousDirectives();
        $this->assertEquals(self::$testDirectives, $directives);

        $_ENV[SystemConfigurationFile::ENV_KEY] = implode(',', self::$testDirectivesAlternative);

        // make sure we can override using Env
        $obj = new SystemConfigurationFile();
        $directives = $obj->getDangerousDirectives();
        $this->assertEquals(self::$testDirectivesAlternative, $directives);

        // make sure we can override using constructor, even when ENV is set
        $obj = new SystemConfigurationFile(self::$testDirectives);
        $directives = $obj->getDangerousDirectives();
        $this->assertEquals(self::$testDirectives, $directives);
    }

    public function testHasDangerousDirectivesWithSafeDirectives()
    {
        $this->assertFalse(
            self::$basicSystemConfigurationFileHandler
                ->hasDangerousDirectives(
                    self::$fileSystem->url() . '/ini/safe.ini'
                )
        );
    }

    public function testHasDangerousDirectivesWithDangerousDirectives()
    {
        $this->assertTrue(
            self::$basicSystemConfigurationFileHandler
                ->hasDangerousDirectives(
                    self::$fileSystem->url() . '/ini/dangerous.ini'
                )
        );
    }

    public function testHasDangerousDirectivesWithDangerousDirectivesAndPHPFile()
    {
        $this->assertTrue(
            self::$basicSystemConfigurationFileHandler
                ->hasDangerousDirectives(
                    self::$fileSystem->url() . '/ini/dangerous.ini.php'
                )
        );
    }

    public function testHasDangerousDirectivesWithDirectivesInComments()
    {
        $this->assertFalse(
            self::$basicSystemConfigurationFileHandler
                ->hasDangerousDirectives(
                    self::$fileSystem->url() . '/ini/commented.ini'
                )
        );
    }
}
