<?php

require_once APP_PATH.'third_party/pbkdf2.php';

class Hm_Test_Session extends PHPUnit_Framework_TestCase {

    private $config;

    /* set things up */
    public function setUp() {
        $this->config = new Hm_Mock_Config();
        setup_db($this->config);
    }

    /* tests for Hm_PHP_Session */
    public function test_build_fingerprint() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTML5');
        $this->assertEquals('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $session->build_fingerprint($request));
    }
    public function test_record_unsaved() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $session->record_unsaved('test');
        $this->assertEquals(array('test'), $session->get('changed_settings'));
    }
    public function test_is_active() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $this->assertFalse($session->is_active());
    }
    public function test_check() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTML5');
        $session->check($request, 'unittestuser', 'unitestpass');
        $this->assertFalse($session->is_active());
    }
    public function test_change_pass() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $this->assertTrue($session->change_pass('unittestuser', 'unittestpass'));
    }
    public function test_create() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTML5');
        //print_r($session->create($request, 'unittestuser', 'unittestpass'));
    }
    public function test_start() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTML5');
        //$session->start($request);
        //echo $session->is_active();
    }
    public function test_session_params() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $request = new Hm_Mock_Request('HTML5');
        $this->assertEquals(array(false, false, false), $session->set_session_params($request));
    }
    public function test_get_and_set() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $this->assertFalse($session->get('test'));
        $session->set('test', 'testvalue');
        $this->assertEquals('testvalue', $session->get('test'));
        $this->assertFalse($session->get('usertest', false, true));
        $session->set('usertest', 'uservalue', true);
        $this->assertEquals('uservalue', $session->get('usertest', false, true));
    }
    public function test_del() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $session->set('test', 'testvalue');
        $this->assertEquals('testvalue', $session->get('test'));
        $session->del('test');
        $this->assertFalse($session->get('test'));
    }
    public function test_end() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $session->set('test', 'testvalue');
        $session->end();
        $this->assertFalse($session->is_active());
    }
    public function test_close_early() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $session->set('test', 'testvalue');
        $session->close_early();
        $this->assertFalse($session->is_active());
    }
    public function test_save_data() {
        $session = new Hm_PHP_Session($this->config, 'Hm_Auth_DB');
        $session->set('test', 'testvalue');
        $session->save_data();
        $this->assertEquals(array(), $_SESSION);
    }
}

?>
