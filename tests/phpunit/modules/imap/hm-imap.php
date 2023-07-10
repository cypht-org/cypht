<?php

use PHPUnit\Framework\TestCase;

class Hm_Test_Hm_IMAP extends TestCase {

    public function setUp(): void {
        define('IMAP_TEST', true);
        require 'bootstrap.php';
        require APP_PATH.'modules/imap/hm-imap.php';
        require APP_PATH.'modules/core/message_functions.php';
        $this->create();
        $this->connect();
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
        $res = $this->debug();
        $this->assertEquals('Logged in successfully as testuser', $res['debug'][2]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_tls() {
        $this->reset();
        $this->config['tls'] = true;
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('Logged in successfully as testuser', $res['debug'][2]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_failed() {
        $this->reset();
        Hm_Functions::$no_stream = true;
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('Could not connect to the IMAP server', $res['debug'][1]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_connect_bad_args() {
        $this->reset();
        unset($this->config['username']);
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('username and password must be set in the connect() config argument', $res['debug'][0]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticate_login() {
        $res = $this->debug();
        $this->assertEquals('Logged in successfully as testuser', $res['debug'][2]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticate_cram() {
        $this->reset();
        $this->config['auth'] = 'cram-md5';
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('Logged in successfully as testuser', $res['debug'][2]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_authenticate_oauth() {
        $this->reset();
        $this->config['auth'] = 'xoauth2';
        $this->connect();
        $res = $this->debug();
        $this->assertEquals('Log in for testuser FAILED', $res['debug'][2]);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_special_use_mailboxes() {
        $boxes = $this->imap->get_special_use_mailboxes();
        $this->assertEquals(array('sent' => 'Sent'), $boxes);
        $boxes = $this->imap->get_special_use_mailboxes('sent');
        $this->assertEquals(array('sent' => 'Sent'), $boxes);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_namespaces() {
        /* TODO: coverage */
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
        $this->assertEquals(array('INBOX' => array( 'parent' => '',
            'delim' => '/', 'name' => 'INBOX', 'name_parts' => array('INBOX'),
            'basename' => 'INBOX', 'realname' => 'INBOX', 'namespace' => '',
            'marked' => false, 'noselect' => false, 'can_have_kids' => true,
            'has_kids' => true), 'Sent' => array( 'parent' => false,
            'delim' => '/', 'name' => 'Sent', 'name_parts' => array('Sent'),
            'basename' => 'Sent', 'realname' => 'Sent', 'namespace' => '',
            'marked' => true, 'noselect' => true, 'can_have_kids' => false,
            'has_kids' => false), 'INBOX/test' => array( 'parent' => 'INBOX',
            'delim' => '/', 'name' => 'INBOX/test', 'name_parts' => array('INBOX', 'test'),
            'basename' => 'test', 'realname' => 'INBOX/test', 'namespace' => '',
            'marked' => false, 'noselect' => false, 'can_have_kids' => true,
            'has_kids' => false)), $this->imap->get_mailbox_list());
        $this->assertEquals(array('INBOX' => array( 'parent' => '',
            'delim' => '/', 'name' => 'INBOX', 'name_parts' => array('INBOX'),
            'basename' => 'INBOX', 'realname' => 'INBOX', 'namespace' => '',
            'marked' => false, 'noselect' => false, 'can_have_kids' => true,
            'has_kids' => true), 'Sent' => array( 'parent' => false,
            'delim' => '/', 'name' => 'Sent', 'name_parts' => array('Sent'),
            'basename' => 'Sent', 'realname' => 'Sent', 'namespace' => '',
            'marked' => true, 'noselect' => true, 'can_have_kids' => false,
            'has_kids' => false), 'INBOX/test' => array( 'parent' => 'INBOX',
            'delim' => '/', 'name' => 'INBOX/test', 'name_parts' => array('INBOX', 'test'),
            'basename' => 'test', 'realname' => 'INBOX/test', 'namespace' => '',
            'marked' => false, 'noselect' => false, 'can_have_kids' => true,
            'has_kids' => false)), $this->imap->get_mailbox_list());
        $this->imap->supported_extensions[] = 'special-use';
        $this->imap->bust_cache('LIST');
        $this->assertEquals(array('INBOX' => array( 'parent' => '',
            'delim' => '/', 'name' => 'INBOX', 'name_parts' => array('INBOX'),
            'basename' => 'INBOX', 'realname' => 'INBOX', 'namespace' => '',
            'marked' => false, 'noselect' => false, 'can_have_kids' => true,
            'has_kids' => true), 'Sent' => array( 'parent' => false,
            'delim' => '/', 'name' => 'Sent', 'name_parts' => array('Sent'),
            'basename' => 'Sent', 'realname' => 'Sent', 'namespace' => '',
            'marked' => true, 'noselect' => true, 'can_have_kids' => false,
            'has_kids' => false), 'INBOX/test' => array( 'parent' => 'INBOX',
            'delim' => '/', 'name' => 'INBOX/test', 'name_parts' => array('INBOX', 'test'),
            'basename' => 'test', 'realname' => 'INBOX/test', 'namespace' => '',
            'marked' => false, 'noselect' => false, 'can_have_kids' => true,
            'has_kids' => false)), $this->imap->get_mailbox_list());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_mailbox_list_lsub() {
        /* TODO: assertions + coverage */
        $this->imap->get_mailbox_list(true);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_select_mailbox() {
        /* TODO: coverage */
        $this->assertEquals(array( 'selected' => 1, 'uidvalidity' => 1422554786,
            'exists' => 93, 'first_unseen' => false, 'uidnext' => 1736, 'flags' =>
            array( '0' => '\Answered', '1' => '\Flagged', '2' => '\Deleted', '3' =>
            '\Seen', '4' => '\Draft',), 'permanentflags' => array( '0' => '\Answered',
            '1' => '\Flagged', '2' => '\Deleted', '3' => '\Seen', '4' => '\Draft', '5' => '\*',
            ), 'recent' => '*', 'nomodseq' => false, 'modseq' => 91323),
            $this->imap->select_mailbox("INBOX"));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_starttls() {
        require_once APP_PATH.'modules/core/functions.php';
        $this->reset();
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
        $this->assertTrue($this->imap->poll());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_message_list() {
        /* TODO: coverage */
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

        $this->assertEquals($res, $this->imap->get_message_content(1731, 0));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_search() {
        /* TODO: coverage and assertions */
        $this->imap->search('ALL', false, array(array('BODY', 'debian')));
        $this->imap->search('ALL', array(1680, 1682), array(array('BODY', 'debian')));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_message_headers() {
        /* TODO: coverage and assertions */
        $res = $this->imap->get_message_headers(1731);
        $this->assertEquals($res['Flags'], '\Seen');
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_start_message_stream() {
        /* TODO: coverage and assertions, add read stream line support */
        $this->imap->start_message_stream(1731, 1);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sort_by_fetch() {
        /* TODO: coverage and assertions */
        $this->assertEquals(array(1732, 4), $this->imap->sort_by_fetch('DATE', false, 'ALL'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_create_mailbox() {
        /* TODO: coverage and assertions */
        $this->assertTrue($this->imap->create_mailbox('foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_rename_mailbox() {
        /* TODO: coverage and assertions */
        $this->assertTrue($this->imap->rename_mailbox('foo', 'bar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_mailbox() {
        /* TODO: coverage and assertions */
        $this->assertTrue($this->imap->delete_mailbox('bar'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_disconnect() {
        $this->disconnect();
        $res = $this->debug();
        $this->assertTrue(array_key_exists('A5 LOGOUT', $res['commands']));
        $this->disconnect();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_action() {
        /* TODO: coverage and assertions */
        $this->assertTrue($this->imap->message_action('DELETE', array(1), 'foo'));
        $this->assertTrue($this->imap->message_action('READ', array(1), 'foo'));
        $this->assertTrue($this->imap->message_action('FLAG', array(1), 'foo'));
        $this->assertTrue($this->imap->message_action('UNFLAG', array(1), 'foo'));
        $this->assertTrue($this->imap->message_action('ANSWERED', array(1), 'foo'));
        $this->assertTrue($this->imap->message_action('UNREAD', array(1), 'foo'));
        $this->assertTrue($this->imap->message_action('UNDELETE', array(1), 'foo'));
        $this->assertTrue($this->imap->message_action('CUSTOM', array(1), 'foo', 'bar'));
        $this->assertTrue($this->imap->message_action('MOVE', array(1), 'Sent'));
        $this->assertTrue($this->imap->message_action('EXPUNGE', array(1)));
        $this->assertTrue($this->imap->message_action('COPY', array(1), 'Sent'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_convert_sequence() {
        /* TODO: coverage */
        $this->assertEquals('1:6', $this->imap->convert_array_to_sequence(array(1,2,3,6)));
        $this->assertEquals(array(1,2,3,4,5,6), $this->imap->convert_sequence_to_array('1:6'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_decode_fld() {
        $this->assertEquals('foo', $this->imap->decode_fld('foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_is_supported() {
        $this->assertEquals(false, $this->imap->is_supported('foo'));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_state() {
        $this->assertEquals('authenticated', $this->imap->get_state());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_show_debug() {
        $this->reset();
        ob_start();
        $this->assertEquals("\nDebug Array\n(\n)\n\n", $this->imap->show_debug());
        $this->assertEquals(array(), $this->imap->show_debug(false, false, true));
        $this->assertEquals(array('debug' => array(), 'commands' => array(), 'responses' => array()), $this->imap->show_debug(true, false, true));
        $this->assertEquals("\nDebug Array\n(\n)\n\nResponse Array\n(\n)\n", $this->imap->show_debug(true, false, false));
        ob_end_clean();
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_quota() {
        $this->imap->supported_extensions[] = 'quota';
        $this->assertEquals(array(array('name' => '', 'max' => 512, 'current' => 10)), $this->imap->get_quota());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_quota_root() {
        $this->imap->supported_extensions[] = 'quota';
        $this->assertEquals(array(array('name' => '', 'max' => 512, 'current' => 10)), $this->imap->get_quota_root("INBOX"));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_enable() {
        $this->assertEquals(array(), $this->imap->enable());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_unselect() {
        $this->assertEquals(true, $this->imap->unselect_mailbox("INBOX"));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_id() {
        $this->imap->supported_extensions[] = 'id';
        $this->assertEquals(array(), $this->imap->id());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_message_sort_order() {
        $this->assertEquals(array("882", "84", "2"), $this->imap->get_message_sort_order());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_google_search() {
        $this->imap->supported_extensions[] = 'x-gm-ext-1';
        $this->assertEquals(array(123, 12344, 5992), $this->imap->google_search("foo"));
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_enable_compression() {
        //$this->imap->supported_extensions[] = 'compress=deflate';
        $this->assertEquals(false, $this->imap->enable_compression());
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_mailbox_page() {
        $res = array(2, array(1731 => array(
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
            'references' => '', 'message_id' =>  ' <E1d8tlQ-00065l-4t@shop>', 'x_auto_bcc' => '')));
        $list = $this->imap->get_mailbox_page('INBOX', 'ARRIVAL', false, 'ALL');
        unset($list[1][1731]['timestamp']);
        unset($list[1][1732]['timestamp']);
        $this->assertEquals($res, $list);
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get_folder_list_by_level() {
        /*$res = array('INBOX' => array('delim' => '/', 'name_parts' => array('INBOX'),
            'basename' => 'INBOX', 'noselect' => false, 'children' => true), 'Sent' => array(
            'delim' => '/', 'name_parts' => array('Sent'), 'basename' => 'Sent',
            'noselect' => true, 'children' => false), 'INBOX/test' => array(
            'delim' => '/', 'name_parts' => array('INBOX', 'test'),
            'basename' => 'test', 'children' => false, 'noselect' => false));
        $this->assertEquals($res, $this->imap->get_folder_list_by_level());*/
    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_first_message_part() {
        $this->assertEquals(array(1, '0123456789'), $this->imap->get_first_message_part(1731, 'text'));
        $this->reset();
        $this->connect();
        $this->assertEquals(array(1, '0123456789'), $this->imap->get_first_message_part(1731, 'text', 'plain'));
    }
    public function tearDown(): void {
        $this->disconnect();
    }
}
