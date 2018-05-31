<?php
use PHPUnit\Framework\TestCase;


class FunctionsTest extends TestCase
{
    public function testGetAbsolutePath()
    {
        $data = array(
            //    Expected              , Input
            array('/'                   , '/..'),
            array('/'                   , '/'),
            array('/home/something'     , '/home/user/../something'),
            array('/home/user/something', '/home/user/something'),
            array('/something'          , '/home/user/../../../something/'),
            array('/something'          , '/home/user/../../something/'),
            array('home/something'      , 'home/user/../something'),
            array('home/something'      , 'home/user/../something/'),
            array('something'           , 'home/user/../../something/'),
        );

        foreach ($data as $counter => $test) {
            list($expected, $input) = $test;
            $this->assertEquals($expected, get_absolute_path($input));
        }
    }
}
