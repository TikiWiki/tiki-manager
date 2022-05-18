<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Application;

use PHPUnit\Framework\TestCase;
use TikiManager\Application\Info;
use TikiManager\Config\App;
use TikiManager\Config\Environment;

/**
 * Class InfoTest
 * @group unit
 */
class InfoTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        \query(Info::SQL_UPDATE_VALUE, [':name' => 'login_attempts', ':value' => 0]);
    }

    public function testGetKeyValue()
    {
        self::assertEquals(0, App::get('info')->get('login_attempts'));

        \query(Info::SQL_UPDATE_VALUE, [':name' => 'login_attempts', ':value' => 7]);

        self::assertEquals(7, App::get('info')->get('login_attempts'));
        self::assertNull(App::get('info')->get('non_existant_key'));
    }

    public function testUpdateInfoValue()
    {
        App::get('info')->update('login_attempts', 8);
        self::assertEquals(8, App::get('info')->get('login_attempts'));
    }

    public function testIsLoginLocked()
    {
        self::assertFalse(App::get('info')->isLoginLocked());

        \query(Info::SQL_UPDATE_VALUE,
            [':name' => 'login_attempts', ':value' => Environment::get('MAX_FAILED_LOGIN_ATTEMPTS', 10)]);

        self::assertTrue(App::get('info')->isLoginLocked());
    }

    public function testIncrementLoginAttempts()
    {
        for ($i = 1; $i < 10; $i++) {
            App::get('info')->incrementLoginAttempts();
            self::assertEquals($i, App::get('info')->get('login_attempts'));
        }
    }

    public function testResetLoginAttempts()
    {
        App::get('info')->update('login_attempts', 8);
        App::get('info')->resetLoginAttempts();

        self::assertEquals(0, App::get('info')->get('login_attempts'));
    }
}
