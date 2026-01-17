<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_PHP_Session
 */
class Hm_Test_PHP_Session extends TestCase {

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
    public function test_build_fingerprint() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $this->assertEquals('f60ed56a9c8275894022fe5a7a1625c33bdb55b729bb4e38962af4d1613eda25', $session->build_fingerprint($request->server));
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_dump() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->set('foo', 'bar');
        $this->assertEquals(array('foo' => 'bar'), $session->dump());
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_record_unsaved() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->record_unsaved('test');
        $this->assertEquals(array('test'), $session->get('changed_settings'));
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_admin() {
        $request = new Hm_Mock_Request('HTTP');
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $this->assertFalse($session->is_admin());
        $session->active = true;
        $this->assertFalse($session->is_admin());

        $this->config->set('admin_users', 'testuser');
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $session->active = true;
        $this->assertFalse($session->is_admin());
        $session->set('username', 'nottestuser');
        $this->assertFalse($session->is_admin());
        $session->set('username', 'testuser');
        $this->assertTrue($session->is_admin());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_active() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $this->assertFalse($session->is_active());
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->check($request, 'unittestuser', 'unittestpass');
        $this->assertTrue($session->is_active());
        $session->destroy($request);

        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->check($request, 'unittestuser', 'unittestpass', false);
        $this->assertTrue($session->is_active());
        $session->destroy($request);

        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $session->check($request, 'nobody', 'knows');
        $this->assertFalse($session->is_active());
        $session->destroy($request);

        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request->cookie['hm_session'] = 'testid';
        $session->check($request);
        $this->assertFalse($session->is_active());
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_fingerprint() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->check($request, 'unittestuser', 'unittestpass');
        $this->assertTrue($session->is_active());
        $session->check_fingerprint($request);
        $this->assertTrue($session->is_active());
        $request->server['HTTP_HOST'] = 'test';
        $session->check_fingerprint($request);
        $this->assertFalse($session->is_active());
        $session->set('fingerprint', false);
        $session->check_fingerprint($request);
        $session->destroy($request);

        $this->config->set('disable_fingerprint', true);
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->check($request, 'unittestuser', 'unittestpass');
        $this->assertTrue($session->is_active());
        $this->assertNull($session->check_fingerprint($request));

    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_change_pass() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $this->assertTrue($session->change_pass('unittestuser', 'unittestpass'));
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_create() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $this->assertEquals(1, $session->create('unittestuser', 'unittestpass'));
        $session->destroy($request);
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_None');
        $this->assertEquals(2, $session->create('unittestuser', 'unittestpass'));
        $session->destroy($request);
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_None');
        $this->assertEquals(2, $session->create('unittestuser', 'unittestpass'));
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $request->cookie['CYPHTID'] = 'asdf';
        $request->cookie['hm_session'] = 'asdf';
        $session->start($request);
        $session->enc_key = 'unittestpass';
        $session->start($request);
        $this->assertTrue($session->is_active());
        $request->invalid_input_detected = true;
        $request->invalid_input_fields = array('test');
        $session->check($request);
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_session_params() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $request->server['HTTP_HOST'] = 'test';
        $this->assertEquals(array(false, 'asdf', 'test'), $session->set_session_params($request));
        $request->tls = true;
        $request->path = 'test';
        $this->assertEquals(array(true, 'test', 'test'), $session->set_session_params($request));
        $session->destroy($request);

        $this->config->set('cookie_domain', 'none');
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $this->assertEquals(array(false, 'asdf', false), $session->set_session_params($request));

        $this->config->set('cookie_path', 'none');
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $this->assertEquals(array(false, 'asdf', false), $session->set_session_params($request));

        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_and_set() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $this->assertFalse($session->get('test'));
        $session->set('test', 'testvalue');
        $this->assertEquals('testvalue', $session->get('test'));
        $this->assertFalse($session->get('usertest', false, true));
        $session->set('usertest', 'uservalue', true);
        $this->assertEquals('uservalue', $session->get('usertest', false, true));
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->set('test', 'testvalue');
        $this->assertEquals('testvalue', $session->get('test'));
        $session->del('test');
        $this->assertFalse($session->get('test'));
        $this->assertFalse($session->del('notfound'));
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_end() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->start($request);
        $this->assertTrue($session->is_active());
        $session->end();
        $this->assertFalse($session->is_active());
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_close_early() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->set('test', 'testvalue');
        $session->close_early();
        $this->assertFalse($session->is_active());
        $session->destroy($request);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_data() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTTP');
        $session->set('test', 'testvalue');
        $session->save_data();
        $this->assertEquals(array(), $_SESSION);
        $session->destroy($request);
    }
}
