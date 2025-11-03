<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the Hm_Servers trait
 */
class Hm_Test_Servers extends TestCase {

    protected $id;

    public function setUp(): void {
        $this->id = Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1));
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'id' => 'abcd'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_add() {
        $this->assertEquals(array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'object' => false, 'connected' => false, 'id' => $this->id), Hm_Server_Wrapper::dump($this->id));
        $this->assertEquals(array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'object' => false, 'connected' => false, 'id' => 'abcd'), Hm_Server_Wrapper::dump('abcd'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_toggle_hidden() {
        Hm_Server_Wrapper::toggle_hidden($this->id, 1);
        $this->assertEquals(array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'object' => false, 'connected' => false, 'id' => $this->id, 'hide' => 1), Hm_Server_Wrapper::dump($this->id));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_fetch() {
        $this->assertTrue(count(Hm_Server_Wrapper::fetch('testuser', 'test')) > 0);
        $this->assertFalse(Hm_Server_Wrapper::fetch('asdf', 'asdf'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_dump() {
        $this->assertEquals(array( $this->id => array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'object' => false, 'connected' => false, 'id' => $this->id), 'abcd' => array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'object' => false, 'connected' => false, 'id' => 'abcd')), Hm_Server_Wrapper::dump());
        $this->assertEquals(array('user' => 'testuser', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'object' => false, 'connected' => false, 'id' => $this->id), Hm_Server_Wrapper::dump($this->id));
        $this->assertEquals(array('pass' => 'testpass', 'user' => 'testuser', 'object' => false, 'connected' => false, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'id' => $this->id), Hm_Server_Wrapper::dump($this->id, true));
        $this->assertEquals(false, Hm_Server_Wrapper::dump('zxcv'));
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'name=' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'pass' => false, 'id' => 'fghi'));
        $this->assertTrue(array_key_exists('nopass', Hm_Server_Wrapper::dump('fghi')));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_forget_credentials() {
        $this->assertEquals(array('pass' => 'testpass', 'user' => 'testuser', 'object' => false, 'connected' => false, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'id' => $this->id), Hm_Server_Wrapper::dump($this->id, true));
        Hm_Server_Wrapper::forget_credentials($this->id);
        $this->assertEquals(array('object' => false, 'connected' => false, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'id' => $this->id), Hm_Server_Wrapper::dump($this->id, true));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_del() {
        $this->assertTrue(Hm_Server_Wrapper::del($this->id));
        $this->assertTrue(Hm_Server_Wrapper::del('abcd'));
        $this->assertEquals(array(), Hm_Server_Wrapper::dump());
        $this->assertFalse(Hm_Server_Wrapper::del($this->id));
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
    public function test_update_oauth2_token() {
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'id' => 'a0'));
        $this->assertFalse(Hm_Server_Wrapper::update_oauth2_token('a0', 'testpass', 3600));
        $this->assertFalse(Hm_Server_Wrapper::update_oauth2_token('a9', 'testpass', 3600));

        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'auth' => 'test', 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'id' => 'a1'));
        $this->assertFalse(Hm_Server_Wrapper::update_oauth2_token('a1', 'testpass', 3600));

        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'auth' => 'xoauth2', 'expiration' => 10, 'name' => 'test', 'server' => 'test', 'port' => 0, 'tls' => 1, 'id' => 'a2'));
        $this->assertTrue(Hm_Server_Wrapper::update_oauth2_token('a2', 'testpass', 3600));

    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect() {
        $this->assertTrue(Hm_Server_Wrapper::connect($this->id) !== false);
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test2', 'server' => 'test2', 'port' => 0, 'tls' => 1, 'id' => 'a1'));
        $this->assertTrue(Hm_Server_Wrapper::connect('a1', false, false, false, true) !== false);
        $this->assertTrue(Hm_Server_Wrapper::connect('a1', false, false, false, true) !== false);

        Hm_Server_Wrapper::add(array('pass' => 'testpass', 'name' => 'test2', 'server' => 'test2', 'port' => 0, 'tls' => 1, 'id' => 'a2'));
        $this->assertFalse(Hm_Server_Wrapper::connect('a2'));
        $this->assertFalse(Hm_Server_Wrapper::connect('a234'));

        Hm_Server_Wrapper::$connected = false;
        Hm_Server_Wrapper::add(array('user' => 'testuser', 'pass' => 'testpass', 'name' => 'test2', 'server' => 'test2', 'port' => 0, 'tls' => 1, 'id' => 'a1'));
        $this->assertFalse(Hm_Server_Wrapper::connect('a1', false, false, false, true) === false);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_cleanup() {
        $this->assertTrue(Hm_Server_Wrapper::service_connect($this->id, 'test', 'testuser', 'testpass', false));
        Hm_Server_Wrapper::clean_up('abcd');
        $atts = Hm_Server_Wrapper::dump('abcd', true);
        $this->assertFalse($atts['connected']);
        Hm_Server_Wrapper::clean_up($this->id);
        Hm_Server_Wrapper::clean_up();
    }
}
