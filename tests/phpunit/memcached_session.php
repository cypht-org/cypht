<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Memcached_Session
 */
class Hm_Test_Memcached_Session extends TestCase {

    public $config;
    public function setUp(): void {
        ini_set('session.use_cookies', '0');
        session_cache_limiter('');
        $this->config = new Hm_Mock_Config();
        $this->config->set('memcached_server', 'asdf');
        $this->config->set('memcached_port', 10);
        $this->config->set('enable_memcached', true);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_memcached_connect() {
        $session = new Hm_Memcached_Session($this->config, 'Hm_Auth_DB');
        $session->connect();
        $this->assertEquals('Hm_Memcached', get_class($session->conn));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_memcached_start_existing() {
        $session = new Hm_Memcached_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->loaded = true;
        $session->start($request);
        $session->set('foo', 'bar');
        $session->save_data();
        $session->start_existing($session->session_key);
        $this->assertEquals('bar', $session->get('foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_memcached_start() {
        $session = new Hm_Memcached_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->loaded = true;
        $session->start($request);
        $this->assertTrue($session->is_active());
        $session->destroy($request);

        $request->cookie['hm_session'] = 'test';
        $session->loaded = false;
        $session->start($request);
        $this->assertFalse($session->is_active());
        $session->destroy($request);

        $request->cookie = array();
        $session->loaded = false;
        $session->start($request);
        $this->assertFalse($session->is_active());
        $session->destroy($request);

        Hm_Mock_Memcached::$set_failure = true;
        $session = new Hm_Memcached_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->loaded = true;
        $session->start($request);
        $this->assertFalse($session->is_active());
        $session->destroy($request);

    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_memcached_end() {
        $session = new Hm_Memcached_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->connect();
        $session->end();
        $this->assertFalse($session->is_active());
        $session->destroy($request);

        $session = new Hm_Memcached_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->loaded = true;
        $session->start($request);
        $session->end();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_memcached_close_early() {
        $session = new Hm_Memcached_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->connect();
        $session->close_early();
        $this->assertFalse($session->is_active());
        $session->destroy($request);
    }
}
