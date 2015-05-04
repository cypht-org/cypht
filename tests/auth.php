<?php

class Hm_IMAP {
    private $connected = false;
    public function get_state() { return $this->connected ? 'authenticated' : false; }
    public function connect() { $this->connected = true; return true; }
}

class Hm_POP3 {
    public function connect() { return true; }
    public function auth() { return true; }
}

class Hm_Test_Auth extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        require APP_PATH.'third_party/pbkdf2.php';
        $this->config = new Hm_Mock_Config();
        setup_db($this->config);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_create() {
        $auth = new Hm_Auth_DB($this->config);
        $auth->delete('unittestuser');
        $this->assertTrue($auth->create('unittestuser', 'unittestpass'));
        $this->assertFalse($auth->create('unittestuser', 'unittestpass'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_credentials() {


        $session = new Hm_Mock_Session();
        $auth = new Hm_Auth_DB($this->config);
        $this->assertFalse($auth->check_credentials('unittestuser', 'notthepass'));
        $this->assertTrue($auth->check_credentials('unittestuser', 'unittestpass'));

        $auth = new Hm_Auth_None($this->config);
        $this->assertTrue($auth->check_credentials('any', 'thing'));
        $this->assertTrue($auth->create('any', 'thing'));

        $auth = new Hm_Auth_IMAP($this->config);
        $this->assertFalse($auth->check_credentials('any', 'thing'));
        $auth = new Hm_Auth_POP3($this->config);
        $this->assertFalse($auth->check_credentials('any', 'thing'));


        $this->config->set('imap_auth_server', 'test');
        $this->config->set('imap_auth_port', 123);
        $this->config->set('imap_auth_tls', false);
        $auth = new Hm_Auth_IMAP($this->config);
        $auth->save_auth_detail($session);
        $this->assertTrue($auth->check_credentials('any', 'thing'));

        $this->config->set('pop3_auth_server', 'test');
        $this->config->set('pop3_auth_port', 123);
        $this->config->set('pop3_auth_tls', false);
        $auth = new Hm_Auth_POP3($this->config);
        $auth->save_auth_detail($session);
        $this->assertTrue($auth->check_credentials('any', 'thing'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_change_pass() {
        $auth = new Hm_Auth_DB($this->config);
        $this->assertTrue($auth->change_pass('unittestuser', 'newpass'));
        $this->assertFalse($auth->check_credentials('unittestuser', 'unittestpass'));
        $this->assertTrue($auth->check_credentials('unittestuser', 'newpass'));
        $this->assertFalse($auth->change_pass('nobody', 'nopass'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete() {
        $auth = new Hm_Auth_DB($this->config);
        $this->assertTrue($auth->delete('unittestuser'));
        $this->assertFalse($auth->delete('nobody'));
        $config = new Hm_Mock_Config();
        $auth = new Hm_Auth_DB($config);
        $this->assertFalse($auth->delete('unittestuser'));
        $auth = new Hm_Auth_DB($this->config);
        $this->assertTrue($auth->create('unittestuser', 'unittestpass'));
    }
    public function tearDown() {
        unset($this->config);
    }
}

?>
