<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Memcached
 */
class Hm_Test_Hm_Memcache extends TestCase {

    public $config;
    public function setUp(): void {
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
