<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Uid_Cache
 */
class Hm_Test_Uid_Cache extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
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

/**
 * tests for Hm_Memcached
 */
class Hm_Test_Hm_Memcache extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        $this->config = new Hm_Mock_Config();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set() {
        $this->config->set('enable_memcached', true);
        $cache = new Hm_Memcached($this->config);
        $this->assertFalse($cache->set('foo', 'bar'));

        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        $this->config->set('enable_memcached', true);

        Hm_Functions::$exists = false;
        $cache = new Hm_Memcached($this->config);
        $this->assertFalse($cache->set('foo', 'bar'));

        Hm_Functions::$exists = true;
        $cache = new Hm_Memcached($this->config);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));

        $this->assertTrue($cache->set('foo', array('bar'), 100, 'asdf'));
        $this->assertEquals(array('bar'), $cache->get('foo', 'asdf'));

        Hm_Functions::$memcache = false;
        $cache = new Hm_Memcached($this->config);
        $this->assertFalse($cache->set('foo', 'bar'));
        Hm_Functions::$memcache = true;
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        $cache = new Hm_Memcached($this->config);
        $this->assertFalse($cache->get('asdf'));
        Hm_Functions::$exists = false;
        $cache = new Hm_Memcached($this->config);
        $this->assertFalse($cache->get('asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_auth() {
        $cache = new Hm_Memcached($this->config);
        $this->assertFalse($cache->close());
        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        $this->config->set('enable_memcached', true);
        $this->config->set('memcached_auth', true);
        $cache = new Hm_Memcached($this->config);
        $this->assertTrue($cache->close());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_close() {
        $cache = new Hm_Memcached($this->config);
        $this->assertFalse($cache->close());
        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        $this->config->set('enable_memcached', true);
        $cache = new Hm_Memcached($this->config);
        $this->assertTrue($cache->close());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del() {
        $cache = new Hm_Memcached($this->config);
        $this->assertFalse($cache->del('foo'));
        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        $this->config->set('enable_memcached', true);
        $cache = new Hm_Memcached($this->config);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));
        $cache->del('foo');
        $this->assertFalse($cache->get('foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_result_code() {
        $cache = new Hm_Memcached($this->config);
        $this->assertFalse($cache->last_err());
        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        $this->config->set('enable_memcached', true);
        $cache = new Hm_Memcached($this->config);
        $this->assertEquals(16, $cache->last_err());
    }
}

/**
 * tests for Hm_Redis
 */
class Hm_Test_Hm_Redis extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        $this->config = new Hm_Mock_Config();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set() {
        $this->config->set('enable_redis', true);
        $cache = new Hm_Redis($this->config);
        $this->assertFalse($cache->set('foo', 'bar'));

        $this->config->set('redis_server', 'asdf');
        $this->config->set('redis_port', 10);
        $this->config->set('enable_redis', true);
        $this->config->set('redis_pass', 'foo');

        Hm_Functions::$exists = false;
        $cache = new Hm_Redis($this->config);
        $this->assertFalse($cache->set('foo', 'bar'));

        Hm_Functions::$exists = true;
        $cache = new Hm_Redis($this->config);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));

        $this->assertTrue($cache->set('foo', array('bar'), 100, 'asdf'));
        $this->assertEquals(array('bar'), $cache->get('foo', 'asdf'));

        Hm_Functions::$redis_on = false;
        $cache = new Hm_Redis($this->config);
        $this->assertFalse($cache->set('foo', 'bar'));
        Hm_Functions::$redis_on = true;

        Hm_Functions::$redis_on = false;
        Hm_Mock_Redis_No::$fail_type = false;
        $cache = new Hm_Redis($this->config);
        $this->assertFalse($cache->set('foo', 'bar'));
        Hm_Functions::$redis_on = true;
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        $cache = new Hm_Redis($this->config);
        $this->assertFalse($cache->get('asdf'));
        Hm_Functions::$exists = false;
        $cache = new Hm_Redis($this->config);
        $this->assertFalse($cache->get('asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_auth() {
        $cache = new Hm_Redis($this->config);
        $this->assertFalse($cache->close());
        $this->config->set('redis_server', 'asdf');
        $this->config->set('redis_port', 10);
        $this->config->set('enable_redis', true);
        $this->config->set('redis_auth', true);
        $cache = new Hm_Redis($this->config);
        $this->assertTrue($cache->close());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_close() {
        $cache = new Hm_Redis($this->config);
        $this->assertFalse($cache->close());
        $this->config->set('redis_server', 'asdf');
        $this->config->set('redis_port', 10);
        $this->config->set('enable_redis', true);
        $cache = new Hm_Redis($this->config);
        $this->assertTrue($cache->close());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del() {
        $cache = new Hm_Redis($this->config);
        $this->assertFalse($cache->del('foo'));
        $this->config->set('redis_server', 'asdf');
        $this->config->set('redis_port', 10);
        $this->config->set('enable_redis', true);
        $cache = new Hm_Redis($this->config);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));
        $cache->del('foo');
        $this->assertFalse($cache->get('foo'));
    }
}

/**
 * tests for generica cache
 */
class Hm_Test_Hm_Cache extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        $this->config = new Hm_Mock_Config();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start() {
        $session = new Hm_Mock_Session();
        $cache = new Hm_Cache($this->config, $session);
        $this->assertEquals('noop', $cache->type);
        $session = new Hm_Mock_Session();
        $this->config->set('allow_session_cache', true);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertEquals('session', $cache->type);
        $this->config->set('enable_memcached', true);
        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertEquals('memcache', $cache->type);
        $this->config->set('enable_redis', true);
        $this->config->set('redis_server', 'asdf');
        $this->config->set('redis_port', 10);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertEquals('redis', $cache->type);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_set() {
        $session = new Hm_Mock_Session();
        $cache = new Hm_Cache($this->config, $session);
        $this->assertFalse($cache->set('foo', 'bar'));
        $this->config->set('allow_session_cache', true);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->config->set('enable_memcached', true);
        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->config->set('enable_redis', true);
        $this->config->set('redis_server', 'asdf');
        $this->config->set('redis_port', 10);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->set('foo', 'bar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        $session = new Hm_Mock_Session();
        $cache = new Hm_Cache($this->config, $session);
        $this->assertEquals('baz', $cache->get('bar', 'baz'));
        $this->config->set('allow_session_cache', true);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));
        $this->assertEquals('baz', $cache->get('bar', 'baz'));
        $this->config->set('enable_memcached', true);
        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));
        $this->config->set('enable_redis', true);
        $this->config->set('redis_server', 'asdf');
        $this->config->set('redis_port', 10);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del() {
        $session = new Hm_Mock_Session();
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->del('foo'));

        $this->config->set('allow_session_cache', true);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));
        $cache->del('foo');
        $this->assertFalse($cache->get('foo', false));
        
        $this->config->set('enable_memcached', true);
        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));
        $cache->del('foo');
        $this->assertFalse($cache->get('foo', false));
        
        $this->config->set('enable_redis', true);
        $this->config->set('redis_server', 'asdf');
        $this->config->set('redis_port', 10);
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));
        $cache->del('foo');
        $this->assertFalse($cache->get('foo', false));
    }
}
