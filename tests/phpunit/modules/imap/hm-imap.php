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
    public function debug() {
        return $this->imap->show_debug(true, true, true);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_working() {
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('Logged in successfully as testuser', $res['debug'][2]);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_tls() {
        $this->config['tls'] = true;
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('Logged in successfully as testuser', $res['debug'][2]);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_failed() {
        Hm_Functions::$no_stream = true;
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('Could not connect to the IMAP server', $res['debug'][1]);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_bad_args() {
        unset($this->config['username']);
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('username and password must be set in the connect() config argument', $res['debug'][0]);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticate_login() {
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('Logged in successfully as testuser', $res['debug'][2]);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticate_cram() {
        $this->config['auth'] = 'cram-md5';
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('Logged in successfully as testuser', $res['debug'][2]);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticate_oauth() {
        $this->config['auth'] = 'xoauth2';
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('Log in for testuser FAILED', $res['debug'][2]);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_special_use_mailboxes() {
        $this->connect();
        $boxes = $this->imap->get_special_use_mailboxes();
        $this->assertEquals(array('sent' => 'Sent'), $boxes);
        $boxes = $this->imap->get_special_use_mailboxes('sent');
        $this->assertEquals(array('sent' => 'Sent'), $boxes);
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_namespaces() {
        $this->connect();
        $this->assertEquals(array(array('delim' => '/', 'prefix' => '', 'class' => 'personal')), $this->imap->get_namespaces());
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_mailbox_list_simple() {
        $this->connect();
        $this->assertEquals(array('INBOX' => array( 'parent' => false,
            'delim' => '/', 'name' => 'INBOX', 'name_parts' => array('INBOX'),
            'basename' => 'INBOX', 'realname' => 'INBOX', 'namespace' => false,
            'marked' => false, 'noselect' => false, 'can_have_kids' => 1,
            'has_kids' => false), 'Sent' => array( 'parent' => false,
            'delim' => '/', 'name' => 'Sent', 'name_parts' => array('Sent'),
            'basename' => 'Sent', 'realname' => 'Sent', 'namespace' => false,
            'marked' => 1, 'noselect' => false, 'can_have_kids' => false,
            'has_kids' => false,)), $this->imap->get_mailbox_list());
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_mailbox_list_lsub() {
        $this->connect();
        $this->imap->get_mailbox_list(true);
        print_r($this->debug());
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_select_mailbox() {
        $this->connect();
        $this->assertEquals(array( 'selected' => 1, 'uidvalidity' => 1422554786,
            'exists' => 93, 'first_unseen' => false, 'uidnext' => 1736, 'flags' =>
            array( '0' => '\Answered', '1' => '\Flagged', '2' => '\Deleted', '3' =>
            '\Seen', '4' => '\Draft',), 'permanentflags' => array( '0' => '\Answered',
            '1' => '\Flagged', '2' => '\Deleted', '3' => '\Seen', '4' => '\Draft', '5' => '\*',
            ), 'recent' => '*', 'nomodseq' => false, 'modseq' => 91323),
            $this->imap->select_mailbox("INBOX"));
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_disconnect() {
        $this->connect();
        $this->disconnect();
        $res = $this->debug();
        $this->assertTrue(array_key_exists('A5 LOGOUT', $res['commands']));
    }
    public function tearDown() {
    }
}
