<?php

class Hm_Test_Hm_IMAP extends PHPUnit_Framework_TestCase {

    public function setUp() {
        define('IMAP_TEST', true);
        require 'bootstrap.php';
        require APP_PATH.'modules/imap/hm-imap.php';
        require APP_PATH.'modules/core/message_functions.php';
        $this->create();
    }
    public function reset() {
        $this->imap = NULL;
        $this->create();
    }
    public function create() {
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
        /* TODO: coverage */
        $this->connect();
        $this->assertEquals(array(array('delim' => '/', 'prefix' => '', 'class' => 'personal')), $this->imap->get_namespaces());
        $this->assertEquals(array(array('delim' => '/', 'prefix' => '', 'class' => 'personal')), $this->imap->get_namespaces());
        $this->disconnect();
        $this->reset();
        Fake_IMAP_Server::$custom_responses['A5 NAMESPACE'] = "A5 BAD error\r\n";
        $this->connect();
        $this->assertEquals(array(), $this->imap->get_namespaces());
        $this->disconnect();
        $this->reset();
        Fake_IMAP_Server::$custom_responses['A3 CAPABILITY'] = "* CAPABILITY IMAP4rev1 LITERAL+ ".
            "SASL-IR LOGIN-REFERRALS ID ENABLE IDLE AUTH=PLAIN STARTTLS\r\n";
        $this->connect();
        $this->assertEquals(array(array('delim' => '/', 'prefix' => '', 'class' => 'personal')), $this->imap->get_namespaces());

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
        /* TODO: assertions + coverage */
        $this->connect();
        $this->imap->get_mailbox_list(true);
        //print_r($this->debug());
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_select_mailbox() {
        /* TODO: coverage */
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
    public function test_starttls() {
        require_once APP_PATH.'modules/core/functions.php';
        Fake_IMAP_Server::$custom_responses['A1 CAPABILITY'] = "* CAPABILITY IMAP4rev1 LITERAL+ ".
            "SASL-IR LOGIN-REFERRALS ID ENABLE IDLE AUTH=PLAIN STARTTLS\r\n";
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('A2 OK Begin TLS negotiation now', $res['responses'][1][0]);
        $this->disconnect();
        $this->reset();
        Fake_IMAP_Server::$custom_responses['A2 STARTTLS'] = "";
        $this->connect();
        $res = $this->debug();
        $this->assertEquals(array(), $res['responses'][1]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_poll() {
        /* TODO: coverage */
        $this->connect();
        $this->assertTrue($this->imap->poll());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_message_list() {
        /* TODO: coverage */
        $this->connect();
        $res = array(1731 => array(
            'uid' => '1731', 'flags' => '\Seen', 'internal_date' => '02-May-2017 16:32:24 -0500',
            'size' => '1940', 'date' => ' Tue, 02 May 2017 16:32:24 -0500', 'from' => ' root <root@shop.jackass.com>',
            'to' => ' root@shop.jackass.com', 'subject' => 'apt-listchanges: news for shop',
            'content-type' => ' text/plain; charset="utf-8"', 'charset' => 'utf-8', 'x-priority' => 0,
            'google_msg_id' => '', 'google_thread_id' => '', 'google_labels' => '', 'list_archive' => '',
            'references' => '', 'message_id' => ' <E1d5fPE-0005Vm-8L@shop>', 'x_auto_bcc' => ''),
            1732 => array('uid' => '1732', 'flags' => '\Seen', 'internal_date' => '11-May-2017 14:28:40 -0500',
            'size' => '1089', 'date' => ' Thu, 11 May 2017 14:28:40 -0500', 'from' => ' root <root@shop.jackass.com>',
            'to' =>  ' root@shop.jackass.com', 'subject' => 'apt-listchanges: news for shop',
            'content-type' => ' text/plain; charset="utf-8"', 'charset' => 'utf-8', 'x-priority' => 0,
            'google_msg_id' => '', 'google_thread_id' => '', 'google_labels' => '', 'list_archive' => '',
            'references' => '', 'message_id' =>  ' <E1d8tlQ-00065l-4t@shop>', 'x_auto_bcc' => ''));

        $list = $this->imap->get_message_list(array(1732, 1731));
        unset($list[1731]['timestamp']);
        unset($list[1732]['timestamp']);
        $this->assertEquals($res, $list);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_message_structure() {
        /* TODO: coverage */
        $res = array ( 1 => array( 'type' => 'text', 'subtype' => 'plain',
            'attributes' => array('charset' => 'utf-8'), 'id' => '', 'description' => '',
            'encoding' => '7bit', 'size' => '1317', 'lines' => '32', 'md5' => '',
            'disposition' => '', 'file_attributes' => '', 'langauge' => '', 'location' => ''));

        $this->connect();
        $this->assertEquals(array(), $this->imap->get_message_structure('foo'));
        $this->assertEquals($res, $this->imap->get_message_structure(1731));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_message_content() {
        /* TODO: coverage */
        $res = "Return-path: <root@shop.jackass.com>\r\n".
            "Envelope-to: root@shop.jackass.com\r\n".
            "Delivery-date: Tue, 02 May 2017 16:32:24 -0500\r\n".
            "Received: from root by shop with local (Exim 4.89)\r\n".
            "        (envelope-from <root@shop.jackass.com>)\r\n".
            "        id 1d5fPE-0005Vm-8L\r\n".
            "        for root@shop.jackass.com; Tue, 02 May 2017 16:32:24 -0500\r\n".
            "Auto-Submitted: auto-generated\r\n".
            "Subject: =?utf-8?q?apt-listchanges=3A_news_for_shop?=\r\n".
            "To: root@shop.jackass.com\r\n".
            "MIME-Version: 1.0\r\n".
            "Content-Type: text/plain; charset=\"utf-8\"\r\n".
            "Content-Transfer-Encoding: 7bit\r\n".
            "Message-Id: <E1d5fPE-0005Vm-8L@shop>\r\n".
            "From: root <root@shop.jackass.com>\r\n".
            "Date: Tue, 02 May 2017 16:32:24 -0500\r\n".
            "\r\n".
            "Test message\r\n".
            "\r\n".
            ")\r\n".
            "A5 OK Fetch completed (0.001 + 0.000 secs).";

        $this->connect();
        $this->assertEquals($res, $this->imap->get_message_content(1731, 0));
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
