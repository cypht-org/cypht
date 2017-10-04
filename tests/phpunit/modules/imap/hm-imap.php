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
    public function test_connect_working() {
        $this->connect();
        $res = $this->imap->show_debug(true, true);
        print_r($res);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_tls() {
        $this->config['tls'] = true;
        $this->connect();
        $res = $this->imap->show_debug(true, true);
        print_r($res);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_failed() {
        Hm_Functions::$no_stream = true;
        $this->connect();
        $res = $this->imap->show_debug(true, true);
        print_r($res);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_bad_args() {
        unset($this->config['username']);
        $this->connect();
        $res = $this->imap->show_debug(true, true);
        print_r($res);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticate_login() {
        $this->connect();
        $res = $this->imap->show_debug(true, true);
        print_r($res);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticate_cram() {
        $this->config['auth'] = 'cram-md5';
        $this->connect();
        $res = $this->imap->show_debug(true, true);
        print_r($res);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticate_oauth() {
        $this->config['auth'] = 'xoauth2';
        $this->connect();
        $res = $this->imap->show_debug(true, true);
        print_r($res);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_special_use_mailboxes() {
        $this->connect();
        $this->imap->get_special_use_mailboxes();
        $this->imap->get_special_use_mailboxes('sent');
        $res = $this->imap->show_debug(true, true);
        print_r($res);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_namespaces() {
        $this->connect();
        $this->imap->get_namespaces();
        $res = $this->imap->show_debug(true, true);
        print_r($res);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_mailbox_list() {
        $this->connect();
        $this->imap->get_mailbox_list();
        $this->imap->get_mailbox_list(true);
        $this->imap->get_mailbox_list();
        $res = $this->imap->show_debug(true, true);
        print_r($res);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_disconnect() {
        /* normal connect */
        $this->connect();
        $res = $this->imap->show_debug(true, true);
        print_r($res);
        $this->disconnect();
    }
    public function tearDown() {
    }
}
