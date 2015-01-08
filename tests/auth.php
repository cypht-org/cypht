<?php


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
        $auth = new Hm_Auth_DB($this->config);
        $this->assertFalse($auth->check_credentials('unittestuser', 'notthepass'));
        $this->assertTrue($auth->check_credentials('unittestuser', 'unittestpass'));
        $auth = new Hm_Auth_None($this->config);
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
