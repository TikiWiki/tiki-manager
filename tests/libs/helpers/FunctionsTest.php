<?php
use PHPUnit\Framework\TestCase;

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
            $this->assertEquals($expected, get_absolute_path($input));
        }
    }
}
