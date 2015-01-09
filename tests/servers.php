<?php

/**
 * tests for the Hm_Server_List trait
 */
class Hm_Test_Server_List extends PHPUnit_Framework_TestCase {

    public function setUp() {
        require 'bootstrap.php';
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1));
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), 3);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add() {
        $this->assertEquals(array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), Hm_Server_Wrapper::dump(0));
        $this->assertEquals(array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), Hm_Server_Wrapper::dump(3));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_dump() {
        $this->assertEquals(array( 0 => array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), 3 => array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1)), Hm_Server_Wrapper::dump());
        $this->assertEquals(array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), Hm_Server_Wrapper::dump(0));
        $this->assertEquals(array('pass' => 'testpass', 'user' => 'testuser', 'object' => false, 'connected' => false, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), Hm_Server_Wrapper::dump(0, true));
        $this->assertEquals(array(), Hm_Server_Wrapper::dump(1));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_forget_credentials() {
        $this->assertEquals(array('pass' => 'testpass', 'user' => 'testuser', 'object' => false, 'connected' => false, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), Hm_Server_Wrapper::dump(0, true));
        Hm_Server_Wrapper::forget_credentials(0);
        $this->assertEquals(array('object' => false, 'connected' => false, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), Hm_Server_Wrapper::dump(0, true));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del() {
        $this->assertTrue(Hm_Server_Wrapper::del(0));
        $this->assertTrue(Hm_Server_Wrapper::del(3));
        $this->assertEquals(array(), Hm_Server_Wrapper::dump());
        $this->assertFalse(Hm_Server_Wrapper::del(0));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_service_connect() {
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), 0);
        $this->assertTrue(Hm_Server_Wrapper::service_connect(0, 'test', 'testuser', 'testpass', false));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect() {
        $this->assertTrue(Hm_Server_Wrapper::connect(0) !== false);
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test2', 'server' => 'test2', 'port' => 0, 'tls' => 1), 1);
        $this->assertTrue(Hm_Server_Wrapper::connect(1, false, false, false, true) !== false);
        $this->assertTrue(Hm_Server_Wrapper::connect(1, false, false, false, true) !== false);
        Hm_Server_Wrapper::add(array('pass' => 'testpass', 'name' => 'test2', 'server' => 'test2', 'port' => 0, 'tls' => 1), 2);
        $this->assertFalse(Hm_Server_Wrapper::connect(2));
        $this->assertFalse(Hm_Server_Wrapper::connect(234));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_cleanup() {
        $this->assertTrue(Hm_Server_Wrapper::service_connect(0, 'test', 'testuser', 'testpass', false));
        Hm_Server_Wrapper::clean_up(3);
        $atts = Hm_Server_Wrapper::dump(3, true);
        $this->assertFalse($atts['connected']);
        Hm_Server_Wrapper::clean_up(0);
    }
}

?>
