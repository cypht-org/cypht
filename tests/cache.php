<?php

class Test_Uid_Cache {
    use Hm_Uid_Cache;
}

class Hm_Test_Cache extends PHPUnit_Framework_TestCase {

    /* set things up */
    public function setUp() {
        Hm_Page_Cache::add('foo', 'bar');
        Test_Uid_Cache::load(array('foo', 'bar'));
    }

    /* tests for Hm_Page_Cache */
    public function test_get() {
        $this->assertEquals('bar', Hm_Page_Cache::get('foo'));
    }
    public function test_dump() {
        $this->assertEquals(array('foo' => array('bar', false)), Hm_Page_Cache::dump());
    }
    public function test_add() {
        $this->assertEquals(1, count(Hm_Page_Cache::dump()));
        Hm_Page_Cache::add('bar', 'foo');
        $this->assertEquals(2, count(Hm_Page_Cache::dump()));
    }
    public function test_concat() {
        $this->assertEquals('bar', Hm_Page_Cache::get('foo'));
        Hm_Page_Cache::concat('foo', 'foo');
        $this->assertEquals('barfoo', Hm_Page_Cache::get('foo'));
        Hm_Page_Cache::concat('foo', 'bar', false, ':');
        $this->assertEquals('barfoo:bar', Hm_Page_Cache::get('foo'));
        Hm_Page_Cache::concat('baz', 'baz');
        $this->assertEquals('baz', Hm_Page_Cache::get('baz'));
    }
    public function test_del() {
        $this->assertEquals('bar', Hm_Page_Cache::get('foo'));
        $this->assertTrue(Hm_Page_Cache::del('foo'));
        $this->assertFalse(Hm_Page_Cache::get('foo'));
        $this->assertFalse(Hm_Page_Cache::del('blah'));
    }
    public function test_flush() {
        $session = new Hm_Mock_Session();
        $this->assertEquals(array('foo' => array('bar', false)), Hm_Page_Cache::dump());
        Hm_Page_Cache::flush($session);
        $this->assertEquals(array(), Hm_Page_Cache::dump());
    }
    public function test_load() {
        $session = new Hm_Mock_Session();
        Hm_Page_Cache::flush($session);
        Hm_Page_Cache::load($session);
        $this->assertEquals(array('foo' => array('bar', false)), Hm_Page_Cache::dump());
    }
    public function test_save() {
        $session = new Hm_Mock_Session();
        Hm_Page_Cache::add('bar', 'foo', true);
        Hm_Page_Cache::save($session);
        $this->assertEquals(array('foo' => array('bar', false)), $session->data['page_cache']);
        $this->assertEquals(array('bar' => array('foo', true)), $session->data['saved_pages']);
    }

    /* test for Hm_Uid_Cache */
    public function test_uid_is_present() {
        $this->assertTrue(Test_Uid_Cache::is_present('foo'));
        $this->assertTrue(Test_Uid_Cache::is_present('bar'));
    }
    public function test_uid_load() {
        Test_Uid_Cache::load(array());
        $this->assertFalse(Test_Uid_Cache::is_present('foobar'));
        Test_Uid_Cache::load(array('foobar'));
        $this->assertTrue(Test_Uid_Cache::is_present('foobar'));
    }
    public function test_uid_dump() {
        $this->assertEquals(array('foo', 'bar'), Test_Uid_Cache::dump());
    }
    public function test_uid_add() {
        $this->assertEquals(false, Test_Uid_Cache::is_present('baz'));
        Test_Uid_Cache::add('baz');
        $this->assertTrue(Test_Uid_Cache::is_present('baz'));
    }
    public function test_uid_remove() {
        $this->assertTrue(Test_Uid_Cache::is_present('bar'));
        Test_Uid_Cache::remove('bar');
        $this->assertEquals(false, Test_Uid_Cache::is_present('bar'));
    }

    /* clean up */
    public function tearDown() {
        $session = new Hm_Mock_Session();
        Hm_Page_Cache::flush($session);
        Test_Uid_Cache::remove('foo');
        Test_Uid_Cache::remove('bar');
    }
}
