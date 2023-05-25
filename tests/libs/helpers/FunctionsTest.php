<?php
namespace TikiManager\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use TikiManager\Libs\Helpers\ApplicationHelper;

/**
 * Class FunctionsTest
 * @group unit
 */
class FunctionsTest extends TestCase
{
    public function testGetAbsolutePath()
    {
        $data = [
            //    Expected              , Input
            ['/'                   , '/..'],
            ['/'                   , '/'],
            ['/home/something'     , '/home/user/../something'],
            ['/home/user/something', '/home/user/something'],
            ['/something'          , '/home/user/../../../something/'],
            ['/something'          , '/home/user/../../something/'],
            ['home/something'      , 'home/user/../something'],
            ['home/something'      , 'home/user/../something/'],
            ['something'           , 'home/user/../../something/'],
        ];

        foreach ($data as $counter => $test) {
            list($expected, $input) = $test;
            $this->assertEquals($expected, ApplicationHelper::getAbsolutePath($input));
        }
    }
}
