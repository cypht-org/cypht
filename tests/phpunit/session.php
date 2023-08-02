<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_PHP_Session
 */
class Hm_Test_PHP_Session extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
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

/**
 * tests for Hm_Redis_Session
 */
class Hm_Test_Redis_Session extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
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

/**
 * tests for Hm_Memcached_Session
 */
class Hm_Test_Memcached_Session extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
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

/**
 * tests for Hm_DB_Session
 */
class Hm_Test_DB_Session extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
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

class Hm_Test_Session_Functions extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php'; 
        $this->config = new Hm_Mock_Config();
        require APP_PATH.'modules/site/lib.php';
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setup_session() {
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_PHP_Session', get_class(($setup->setup_session())));

        $this->config->set('session_type', 'DB');
        $this->config->set('auth_type', 'DB');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_DB_Session', get_class(($setup->setup_session())));

        $this->config->mods[] = 'dynamic_login';
        $this->config->set('auth_type', 'dynamic');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_DB_Session', get_class(($setup->setup_session())));

        $this->config->set('session_type', 'asdf');
        $this->config->set('auth_type', 'DB');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_PHP_Session', get_class(($setup->setup_session())));

        $this->config->set('session_type', 'custom');
        $this->config->set('auth_type', 'custom');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Custom_Session', get_class(($setup->setup_session())));

        $this->config->set('session_type', 'REDIS');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_Redis_Session', get_class(($setup->setup_session())));

        $this->config->set('session_type', 'MEM');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_Memcached_Session', get_class(($setup->setup_session())));

        Hm_Functions::$exists = false;
        $this->config->set('session_type', 'PHP');
        $this->config->set('auth_type', 'asdf');
        $setup = new Hm_Session_Setup($this->config);
        $this->assertEquals('Hm_PHP_Session', get_class(($setup->setup_session())));

    }
    public function tearDown(): void {
        unset($this->config);
    }
}

?>
