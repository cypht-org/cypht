<?php

class Hm_Server_Wrapper {
    use Hm_Server_List;
    public function service_connect($id, $server, $user, $pass, $cache) {
        self::$server_list[$id]['object'] = new stdClass();
        self::$server_list[$id]['connected'] = true;
        return true;
    }
}
class Hm_Test_Servers extends PHPUnit_Framework_TestCase {

    public function test_add() {
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1));
        $this->assertEquals(array(array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1)), Hm_Server_Wrapper::dump());
    }
    public function test_dump() {
        $this->assertEquals(array(array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1)), Hm_Server_Wrapper::dump());
        $this->assertEquals(array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), Hm_Server_Wrapper::dump(0));
        $this->assertEquals(array('pass' => 'testpass', 'user' => 'testuser', 'object' => false, 'connected' => false, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), Hm_Server_Wrapper::dump(0, true));
        $this->assertEquals(array(), Hm_Server_Wrapper::dump(1));
    }
    public function test_forget_credentials() {
        $this->assertEquals(array('pass' => 'testpass', 'user' => 'testuser', 'object' => false, 'connected' => false, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), Hm_Server_Wrapper::dump(0, true));
        Hm_Server_Wrapper::forget_credentials(0);
        $this->assertEquals(array('object' => false, 'connected' => false, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1), Hm_Server_Wrapper::dump(0, true));
    }
    public function test_del() {
        Hm_Server_Wrapper::del(0);
        $this->assertEquals(array(), Hm_Server_Wrapper::dump());
    }
    public function test_service_connect() {
        $this->assertTrue(Hm_Server_Wrapper::service_connect(0, 'test', 'testuser', 'testpass', false));
    }
    public function test_connect() {
        $this->assertTrue(Hm_Server_Wrapper::connect(0, false, 'test', 'testuser', 'testpass') !== false);
    }
    public function test_cleanup() {
        $this->assertTrue(Hm_Server_Wrapper::service_connect(0, 'test', 'testuser', 'testpass', false));
        Hm_Server_Wrapper::clean_up(0);
        $atts = Hm_Server_Wrapper::dump(0, true);
        $this->assertFalse($atts['connected']);
    }

}

?>
