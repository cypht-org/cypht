<?php

class Hm_Test_Hm_IMAP extends PHPUnit_Framework_TestCase {

    public function setUp() {
        define('IMAP_TEST', true);
        require 'bootstrap.php';
        require APP_PATH.'modules/imap/hm-imap.php';
        $this->imap = new Hm_IMAP();
        $this->config = array(
            'server' => 'foo',
            'port' => 0,
            'username' => 'testuser',
            'password' => 'testpass'
        );
    }
    public function connect() {
        $this->imap->connect($this->config);
    }
    public function disconnect() {
        $this->imap->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect() {
        /* normal connect */
        $this->connect();
        $this->imap->show_debug();
        $this->disconnect();
        /* tls connect */
        $this->config['tls'] = true;
        $this->connect();
        $this->imap->show_debug();
        $this->disconnect();
        /* failed connection */
        Hm_Functions::$no_stream = true;
        $this->connect();
        $this->imap->show_debug();
        /* bad args */
        unset($this->config['username']);
        $this->connect();
        $this->imap->show_debug();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_disconnect() {
        /* normal connect */
        $this->connect();
        $this->imap->show_debug();
        $this->disconnect();
    }
    public function tearDown() {
    }
}
