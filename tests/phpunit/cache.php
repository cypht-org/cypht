<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Uid_Cache
 */
class Hm_Test_Uid_Cache extends TestCase {

    public function setUp(): void {
        Test_Uid_Cache::load(array(array('foo', 'bar'),array()));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_is_read() {
        $this->assertTrue(Test_Uid_Cache::is_read('foo'));
        $this->assertTrue(Test_Uid_Cache::is_read('bar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_is_unread() {
        $this->assertFalse(Test_Uid_Cache::is_unread('foo'));
        Test_Uid_Cache::unread('bar');
        $this->assertTrue(Test_Uid_Cache::is_unread('bar'));
        $this->assertFalse(Test_Uid_Cache::is_read('bar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_load() {
        Test_Uid_Cache::load(array(array(),array()));
        $this->assertFalse(Test_Uid_Cache::is_read('foobar'));
        Test_Uid_Cache::load(array(array('foobar'), array('foobar')));
        $this->assertTrue(Test_Uid_Cache::is_read('foobar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_dump() {
        $this->assertEquals(array(array('foo', 'bar'),array()), Test_Uid_Cache::dump());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_uid_read() {
        $this->assertEquals(false, Test_Uid_Cache::is_read('baz'));
        Test_Uid_Cache::unread('baz');
        Test_Uid_Cache::read('baz');
        $this->assertTrue(Test_Uid_Cache::is_read('baz'));
    }
    public function tearDown(): void {
        Test_Uid_Cache::load(array(),array());
    }
}
