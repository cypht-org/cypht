<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Transform extends TestCase {
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_stringify() {
        $this->assertEquals('{"foo":"YmFy"}', Hm_Transform::stringify(array('foo' => 'bar')));
        $this->assertEquals('', Hm_Transform::stringify(NULL));
        $this->assertEquals('asdf', Hm_Transform::stringify('asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unstringify() {
        $test = Hm_Transform::stringify(array('foo' => 'bar', 'baz' => array('test' => 'asdf')));
        $this->assertEquals(array('foo' => 'bar', 'baz' => array('test' => 'asdf')), Hm_Transform::unstringify($test));
        $this->assertFalse(Hm_Transform::unstringify(array()));
        $this->assertFalse(Hm_Transform::unstringify('asdf'));
        $this->assertEquals('asdf', Hm_Transform::unstringify('asdf', false, true));
        $this->assertEquals(array('foo' => 'bar'), Hm_Transform::unstringify('a:1:{s:3:"foo";s:4:"YmFy";}'));
        $int_test = Hm_Transform::stringify(array('foo' => 1));
        $this->assertEquals(array('foo' => 1), Hm_Transform::unstringify($int_test));
    }
}
