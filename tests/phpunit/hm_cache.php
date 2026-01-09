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
