<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Msgs
 */
class Hm_Test_Msgs extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add() {
        Hm_Msgs::add('test msg');
        $msgs = Hm_Msgs::get();
        $this->assertTrue(in_array('test msg', $msgs, true));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_flush() {
        Hm_Msgs::add('test msg');
        $this->assertTrue(in_array('test msg', Hm_Msgs::get(), true));
        Hm_Msgs::flush();
        $this->assertEquals(array(), Hm_Msgs::get());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        Hm_Msgs::add('test msg');
        $this->assertTrue(in_array('test msg', Hm_Msgs::get(), true));
        Hm_Msgs::add('msg two');
        $this->assertTrue(in_array('msg two', Hm_Msgs::get(), true));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_str() {
        $this->assertEquals('string: test', Hm_Msgs::str('test'));
        $this->assertEquals('integer: 0', Hm_Msgs::str(0));
        $this->assertEquals('double: 0.1', Hm_Msgs::str(.1));
        $this->assertEquals('array()', flatten(Hm_Msgs::str(array())));
        $this->assertEquals('stdclassobject()', flatten(Hm_Msgs::str(new stdClass)));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_show() {
        Hm_Msgs::add('msg');
        $this->assertEquals("Array\n(\n    [0] => SUCCESS: msg\n)\n", Hm_Msgs::show());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_elog() {
        $this->assertNull(elog('test'));
    }
}
