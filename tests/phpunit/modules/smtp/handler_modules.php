<?php

use PHPUnit\Framework\TestCase;

class Smtp_Handler_Test {
    public $post = array();
    public $get = array();
    public $user_config = array();
    public $config = array();
    public $input = array();
    public $modules = array('imap', 'smtp', 'profiles');
    public $mod = false;
    public $tls = false;
    public $rtype = 'HTTP';
    public $session = array();
    public $req_obj = false;
    public $ses_obj = false;
    public $set;
    public $module_exec;

    public function __construct($name, $set) {
        $this->mod = $name;
        $this->set = $set;
    }

    public function prep() {
        $config = new Hm_Mock_Config();
        $config->mods = $this->modules;
        foreach ($this->config as $name => $val) {
            $config->set($name, $val);
        }
        $this->module_exec = new Hm_Module_Exec($config);
        $this->module_exec->user_config = new Hm_Mock_Config();
        foreach ($this->user_config as $name => $val) {
            $this->module_exec->user_config->set($name, $val);
        }
        $this->req_obj = new Hm_Mock_Request($this->rtype);
        $this->req_obj->tls = $this->tls;
        $this->req_obj->post = $this->post;
        $this->req_obj->get = $this->get;
        $this->ses_obj = new Hm_Mock_Session();
        foreach ($this->session as $name => $val) {
            $this->ses_obj->set($name, $val);
        }
        Hm_Handler_Modules::add('test', $this->mod, false, false, false, true, $this->set);
        $this->module_exec->handler_response = $this->input;
        Hm_IMAP_List::init($this->module_exec->user_config, $this->ses_obj);
    }

    public function run() {
        $this->prep();
        $this->module_exec->run_handler_modules($this->req_obj, $this->ses_obj, 'test');
        return $this->module_exec;
    }
}

class Hm_Test_Smtp_Handler_Modules extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'modules/imap/functions.php';
        require_once APP_PATH.'modules/imap/hm-imap.php';
        require_once APP_PATH.'modules/smtp/modules.php';
        Hm_IMAP::$connect_calls = 0;
        Hm_IMAP::$allow_connection = false;
    }

    private function imapServer($overrides = array()) {
        return array_merge(array(
            'name' => 'Test account',
            'server' => 'imap.example.com',
            'port' => 993,
            'tls' => 1,
            'user' => 'user@example.com',
            'pass' => 'secret',
            'type' => 'imap',
        ), $overrides);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hidden_server_is_skipped_without_connecting() {
        Hm_IMAP_List::init(new Hm_Mock_Config(), new Hm_Mock_Session());
        Hm_IMAP_List::add($this->imapServer(array('hide' => true, 'id' => 'hidden')));

        $test = new Smtp_Handler_Test('send_scheduled_messages', 'smtp');
        $res = $test->run();

        $this->assertSame(0, Hm_IMAP::$connect_calls);
        $this->assertEquals(0, $res->handler_response['scheduled_msg_count']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_visible_server_is_attempted() {
        Hm_IMAP_List::init(new Hm_Mock_Config(), new Hm_Mock_Session());
        Hm_IMAP_List::add($this->imapServer(array('id' => 'visible')));

        $test = new Smtp_Handler_Test('send_scheduled_messages', 'smtp');
        $test->run();

        $this->assertSame(1, Hm_IMAP::$connect_calls);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_failed_server_is_skipped_on_next_poll_within_cooldown() {
        Hm_IMAP_List::init(new Hm_Mock_Config(), new Hm_Mock_Session());
        Hm_IMAP_List::add($this->imapServer(array('id' => 'visible')));

        $test = new Smtp_Handler_Test('send_scheduled_messages', 'smtp');
        $test->run();
        $this->assertSame(1, Hm_IMAP::$connect_calls);

        $test2 = new Smtp_Handler_Test('send_scheduled_messages', 'smtp');
        $test2->session = $test->ses_obj->data;
        $test2->run();

        $this->assertSame(1, Hm_IMAP::$connect_calls);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_failed_server_is_retried_after_cooldown_expires() {
        Hm_IMAP_List::init(new Hm_Mock_Config(), new Hm_Mock_Session());
        Hm_IMAP_List::add($this->imapServer(array('id' => 'visible')));

        $test = new Smtp_Handler_Test('send_scheduled_messages', 'smtp');
        $test->run();
        $this->assertSame(1, Hm_IMAP::$connect_calls);

        $failed = $test->ses_obj->data['scheduled_send_failed_servers'];
        $failed['visible'] = time() - 301;

        $test2 = new Smtp_Handler_Test('send_scheduled_messages', 'smtp');
        $test2->session = $test->ses_obj->data;
        $test2->session['scheduled_send_failed_servers'] = $failed;
        $test2->run();

        $this->assertSame(2, Hm_IMAP::$connect_calls);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_successful_server_is_cleared_from_failed_cache() {
        Hm_IMAP_List::init(new Hm_Mock_Config(), new Hm_Mock_Session());
        Hm_IMAP_List::add($this->imapServer(array('id' => 'visible')));

        $test = new Smtp_Handler_Test('send_scheduled_messages', 'smtp');
        $test->run();
        $this->assertArrayHasKey('visible', $test->ses_obj->data['scheduled_send_failed_servers']);

        $failed = $test->ses_obj->data['scheduled_send_failed_servers'];
        $failed['visible'] = time() - 301;

        Hm_IMAP::$allow_connection = true;
        $test2 = new Smtp_Handler_Test('send_scheduled_messages', 'smtp');
        $test2->session = $test->ses_obj->data;
        $test2->session['scheduled_send_failed_servers'] = $failed;
        $test2->run();

        $this->assertArrayNotHasKey('visible', $test2->ses_obj->data['scheduled_send_failed_servers']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_failure_restores_posted_attachments_over_session() {
        $attachment_dir = sys_get_temp_dir().'/cypht-send-fail-'.bin2hex(random_bytes(4));
        $user_dir = $attachment_dir.'/'.md5('alice');
        mkdir($user_dir, 0700, true);
        $plain = 'attachment-body';
        try {
            $test = new Smtp_Handler_Test('load_smtp_servers_from_config', 'smtp');
            $test->config = array('attachment_dir' => $attachment_dir);
            $test->session = array(
                'username' => 'alice',
                'uploaded_files' => array(
                    '7' => array(array(
                        'name' => 'stale.txt',
                        'type' => 'text/plain',
                        'size' => 4,
                        'filename' => $user_dir.'/stale.txt',
                    )),
                ),
            );
            $test->post = array(
                'smtp_send' => 'Send',
                'compose_subject' => 'Hello',
                'compose_body' => 'Body text',
                'compose_smtp_id' => '1',
                'draft_id' => '7',
                'post_archive' => '0',
                'next_email_post' => '',
                'compose_to' => 'alfred@example.com',
                'send_uploaded_files' => json_encode(array('notes.pdf')),
            );
            $test->prep();
            Hm_Request_Key::load($test->ses_obj, $test->req_obj, false);
            file_put_contents(
                $user_dir.'/notes.pdf',
                Hm_Crypt::ciphertext($plain, Hm_Request_Key::generate())
            );
            Hm_Handler_Modules::add(
                'test',
                'process_compose_form_submit',
                false,
                'load_smtp_servers_from_config',
                'after',
                true,
                'smtp'
            );
            $test->module_exec->run_handler_modules($test->req_obj, $test->ses_obj, 'test');

            $response = $test->module_exec->handler_response;
            $files = $response['uploaded_files'];
            $this->assertCount(1, $files);
            $this->assertSame('notes.pdf', $files[0]['name']);
            $this->assertSame('application/pdf', $files[0]['type']);
            $this->assertSame(strlen($plain), $files[0]['size']);
            $this->assertTrue($response['no_redirect']);
            $this->assertSame('Hello', $response['compose_draft']['draft_subject']);
            $this->assertSame('alfred@example.com', $response['compose_draft']['draft_to']);
            $this->assertSame('1', $response['compose_draft']['draft_compose_smtp_id']);
            $this->assertFileExists($user_dir.'/notes.pdf');
        } finally {
            rrmdir($attachment_dir);
        }
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_send_failure_with_no_attachments_clears_session_list() {
        $test = new Smtp_Handler_Test('load_smtp_servers_from_config', 'smtp');
        $test->config = array('attachment_dir' => sys_get_temp_dir());
        $test->session = array(
            'username' => 'alice',
            'uploaded_files' => array(
                '7' => array(array(
                    'name' => 'stale.txt',
                    'type' => 'text/plain',
                    'size' => 4,
                    'filename' => '/tmp/stale.txt',
                )),
            ),
        );
        $test->post = array(
            'smtp_send' => 'Send',
            'compose_subject' => 'Hello',
            'compose_body' => 'Body text',
            'compose_smtp_id' => '1',
            'draft_id' => '7',
            'post_archive' => '0',
            'next_email_post' => '',
            'compose_to' => 'alfred@example.com',
        );
        $test->prep();
        Hm_Handler_Modules::add(
            'test',
            'process_compose_form_submit',
            false,
            'load_smtp_servers_from_config',
            'after',
            true,
            'smtp'
        );
        $test->module_exec->run_handler_modules($test->req_obj, $test->ses_obj, 'test');

        $response = $test->module_exec->handler_response;
        $this->assertSame(array(), $response['uploaded_files']);
        $this->assertSame('Hello', $response['compose_draft']['draft_subject']);
    }
}
