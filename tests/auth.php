<?php

require APP_PATH.'third_party/pbkdf2.php';

class Hm_Test_Auth extends PHPUnit_Framework_TestCase {

    private $config;

    /* set things up */
    public function setUp() {
        $this->config = new Hm_Mock_Config();
        setup_db($this->config);
    }
    public function test_create() {
        $auth = new Hm_Auth_DB($this->config);
        $this->assertTrue($auth->create('unittestuser', 'unittestpass'));
        $this->assertFalse($auth->create('unittestuser', 'unittestpass'));
    }
    public function test_check_credentials() {
        $auth = new Hm_Auth_DB($this->config);
        $this->assertFalse($auth->check_credentials('unittestuser', 'notthepass'));
        $this->assertTrue($auth->check_credentials('unittestuser', 'unittestpass'));
    }
    public function test_change_pass() {
        $auth = new Hm_Auth_DB($this->config);
        $this->assertTrue($auth->change_pass('unittestuser', 'newpass'));
        $this->assertFalse($auth->check_credentials('unittestuser', 'unittestpass'));
        $this->assertTrue($auth->check_credentials('unittestuser', 'newpass'));
    }
    public function test_delete() {
        $auth = new Hm_Auth_DB($this->config);
        $this->assertTrue($auth->delete('unittestuser'));
    }
}

?>
