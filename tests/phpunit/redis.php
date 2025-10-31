<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Redis
 */
class Hm_Test_Hm_Redis extends TestCase {

    public $config;
    public function setUp(): void {
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
