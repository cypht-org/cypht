<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for generica cache
 */
class Hm_Test_Hm_Cache extends TestCase {

    public $config;
    public function setUp(): void {
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
    public function test_memcache_get_returns_default_on_connection_failure() {
        $this->config->set('enable_memcached', true);
        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        Hm_Mock_Memcached::$result_code = 3;
        $session = new Hm_Mock_Session();
        $cache = new Hm_Cache($this->config, $session);
        $this->assertEquals('memcache', $cache->type);
        $this->assertEquals(array(), $cache->get('imap_folders_imap_test_', array()));
        Hm_Mock_Memcached::$result_code = 16;
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

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_noop_cache_reconnect_returns_true() {
        $noop = new Hm_Noop_Cache();
        $this->assertTrue($noop->reconnect());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_noop_cache_set_returns_false() {
        $noop = new Hm_Noop_Cache();
        $this->assertFalse($noop->set('key', 'val', 600, ''));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_cache_reconnect_delegates_to_backend() {
        $session = new Hm_Mock_Session();
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->reconnect());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_cache_get_returns_default_for_noop_backend() {
        $session = new Hm_Mock_Session();
        $cache = new Hm_Cache($this->config, $session);
        $this->assertEquals('my_default', $cache->get('nonexistent_key', 'my_default'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_cache_del_on_noop_backend_returns_true() {
        $session = new Hm_Mock_Session();
        $cache = new Hm_Cache($this->config, $session);
        $this->assertTrue($cache->del('any_key'));
    }
}
