<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Redis_Session
 */
class Hm_Test_Redis_Session extends TestCase {

    public $config;
    public function setUp(): void {
        ini_set('session.use_cookies', '0');
        session_cache_limiter('');
        $this->config = new Hm_Mock_Config();
        $this->config->set('redis_server', 'asdf');
        $this->config->set('redis_port', 10);
        $this->config->set('enable_redis', true);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_redis_connect() {
        $session = new Hm_Redis_Session($this->config, 'Hm_Auth_DB');
        $session->connect();
        $this->assertEquals('Hm_Redis', get_class($session->conn));
    }
}
