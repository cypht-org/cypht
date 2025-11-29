<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_DB_Session
 */
class Hm_Test_DB_Session extends TestCase {

    public $config;
    public function setUp(): void {
        ini_set('session.use_cookies', '0');
        session_cache_limiter('');
        $this->config = new Hm_Mock_Config();
        setup_db($this->config);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_db_connect() {
        $_POST['user'] = 'unittestusers';
        $_POST['pass'] = 'unittestpass';
        $session = new Hm_DB_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $this->assertTrue($session->connect());
        $session->destroy($request);
        $config = new Hm_Mock_Config();
        $session = new Hm_DB_Session($config, 'Hm_Auth_DB');
        $this->assertFalse($session->connect());
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_db_end() {
        $session = new Hm_DB_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->end();
        $this->assertFalse($session->is_active());
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_db_start() {
        $session = new Hm_DB_Session($this->config, 'Hm_Auth_DB');
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
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_db_start_existing_session() {
        $session = new Hm_DB_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->loaded = true;
        $session->start($request);
        $this->assertTrue($session->is_active());
        $key = $session->session_key;
        $session->end();
        $session->start_existing($key);
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_db_close_early() {
        $session = new Hm_DB_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->close_early();
        $this->assertFalse($session->is_active());
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_db_save_data() {
        $session = new Hm_DB_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->loaded = true;
        $session->start($request);
        $this->assertEquals(1, $session->save_data());
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_plaintext() {
        $session = new Hm_DB_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $this->assertEquals(array('data'), ($session->plaintext($session->ciphertext(array('data')))));
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_key() {
        $session = new Hm_DB_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $request->cookie['hm_id'] = 'test';
        $session->get_key($request);
        $this->assertEquals('test', $session->enc_key);
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_secure_cookie() {
        $session = new Hm_DB_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $request->tls = true;
        $request->path = 'test';
        $this->assertTrue($session->secure_cookie($request, 'name', 'value'));
        $this->assertTrue($session->secure_cookie($request, 'name', 'value', '/', 'http://localhost:123'));
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_insert_session_row() {
        $request = new Hm_Mock_Request('HTTP');
        $session = new Hm_DB_Session($this->config, 'Hm_Auth_None');
        $this->assertFalse($session->insert_session_row());
        $session->connect();
        $this->assertEquals(1, $session->insert_session_row());
        $session->destroy($request);

        $config = new Hm_Mock_Config();
        $session = new Hm_DB_Session($config, 'Hm_Auth_DB');
        $session->connect();
        $this->assertFalse($session->insert_session_row());
        $session->destroy($request);

    }
    public function tearDown(): void {
        unset($this->config);
    }
}
