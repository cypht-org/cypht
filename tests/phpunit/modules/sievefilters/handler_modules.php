<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/sieve_test_helpers.php';

class Hm_Test_Sievefilters_Handler_Modules extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'modules/sievefilters/modules.php';
        Hm_Test_Sieve_Client::$scripts = array();
        Hm_Test_Sieve_Client::$activated = '';
        Hm_Test_Sieve_Client::$renamed = array();
        Hm_Msgs::flush();
    }

    private function editableFilterScript($test_type = 'allof') {
        $conditions_json = json_encode(array(array(
            'condition' => 'from',
            'type' => 'Contains',
            'value' => 'sender@example.com',
        )));
        $actions_json = json_encode(array(array(
            'action' => 'keep',
            'value' => '',
        )));

        return implode("\n", array(
            "# CYPHT CONFIG HEADER - DON'T REMOVE",
            '# '.base64_encode($conditions_json),
            '# '.base64_encode($actions_json),
            '# '.base64_encode('message_list'),
            '',
            'if '.$test_type.' (header :contains "From" ["sender@example.com"]) {',
            '    keep;',
            '}',
        ));
    }

    private function imapServersConfig() {
        return array(
            'serverA' => array(
                'name' => 'Primary Account',
                'sieve_config_host' => 'tls://sieve.example.com:4190',
                'server' => 'imap.example.com',
                'user' => 'user@example.com',
                'pass' => 'secret',
            ),
        );
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_classic_filter_modal_contains_add_condition_and_action_buttons() {
        $content = get_classic_filter_modal_content();

        $this->assertStringContainsString('sieve_add_condition_modal_button', $content);
        $this->assertStringContainsString('Add Condition', $content);
        $this->assertStringContainsString('filter_modal_add_action_btn', $content);
        $this->assertStringContainsString('Add Action', $content);
        $this->assertStringContainsString('stop_filtering', $content);
        $this->assertStringContainsString('Filter Name:', $content);
        $this->assertStringContainsString('Priority:', $content);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_script_modal_contains_required_fields() {
        $content = get_script_modal_content();

        $this->assertStringContainsString('edit_script_modal', $content);
        $this->assertStringContainsString('modal_sieve_script_name', $content);
        $this->assertStringContainsString('modal_sieve_script_priority', $content);
        $this->assertStringContainsString('modal_sieve_script_textarea', $content);
        $this->assertStringContainsString('Filter Name:', $content);
        $this->assertStringContainsString('Priority:', $content);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_mailbox_name_from_list_path() {
        $test = new Sieve_Handler_Test('load_mailbox_name', 'sievefilters');
        $test->get = array('list_path' => 'imap_serverA_INBOX');
        $test->user_config = array(
            'imap_servers' => array(
                'serverA' => array('name' => 'Primary Account', 'sieve_config_host' => 'tls://sieve.example.com:4190'),
            ),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertEquals('Primary Account', $res->handler_response['mailbox_name']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_edit_filter_loads_conditions_actions_and_test_type() {
        Hm_Test_Sieve_Client::$scripts = array(
            'important_sender-10-cyphtfilter' => $this->editableFilterScript('allof'),
        );

        $test = new Sieve_Handler_Test('sieve_edit_filter', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'sieve_script_name' => 'important_sender-10-cyphtfilter',
        );
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertEquals(json_encode('[{"condition":"from","type":"Contains","value":"sender@example.com"}]'), $res->handler_response['conditions']);
        $this->assertEquals(json_encode('[{"action":"keep","value":""}]'), $res->handler_response['actions']);
        $this->assertEquals('ALLOF', $res->handler_response['test_type']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_edit_script_outputs_existing_script() {
        Hm_Test_Sieve_Client::$scripts = array(
            'manual_script-15-cypht' => "require [\"fileinto\"];\nkeep;",
        );

        $test = new Sieve_Handler_Test('sieve_edit_script', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'sieve_script_name' => 'manual_script-15-cypht',
        );
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertEquals("require [\"fileinto\"];\nkeep;", $res->handler_response['script']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_delete_filter_removes_script_and_rebuilds_main_script() {
        Hm_Test_Sieve_Client::$scripts = array(
            'main_script' => 'require ["include"];',
            'important_sender-10-cyphtfilter' => $this->editableFilterScript('allof'),
            'manual_script-15-cypht' => "require [\"fileinto\"];\nkeep;",
        );

        $test = new Sieve_Handler_Test('sieve_delete_filter', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'sieve_script_name' => 'important_sender-10-cyphtfilter',
        );
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertTrue($res->handler_response['script_removed']);
        $this->assertArrayNotHasKey('important_sender-10-cyphtfilter', Hm_Test_Sieve_Client::$scripts);
        $this->assertStringContainsString('manual_script-15-cypht', Hm_Test_Sieve_Client::$scripts['main_script']);
        $this->assertEquals('main_script', Hm_Test_Sieve_Client::$activated);
        $this->assertEquals(array('Script removed'), Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_delete_script_removes_script_and_rebuilds_main_script() {
        Hm_Test_Sieve_Client::$scripts = array(
            'main_script' => 'require ["include"];',
            'important_sender-10-cyphtfilter' => $this->editableFilterScript('allof'),
            'manual_script-15-cypht' => "require [\"fileinto\"];\nkeep;",
        );

        $test = new Sieve_Handler_Test('sieve_delete_script', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'sieve_script_name' => 'manual_script-15-cypht',
        );
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertTrue($res->handler_response['script_removed']);
        $this->assertArrayNotHasKey('manual_script-15-cypht', Hm_Test_Sieve_Client::$scripts);
        $this->assertStringContainsString('important_sender-10-cyphtfilter', Hm_Test_Sieve_Client::$scripts['main_script']);
        $this->assertEquals('main_script', Hm_Test_Sieve_Client::$activated);
        $this->assertEquals(array('Script removed'), Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_filters_enabled_message_content_outputs_client_when_configured() {
        $test = new Sieve_Handler_Test('sieve_filters_enabled_message_content', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array('imap_server_id' => 'serverA');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertTrue($res->handler_response['sieve_filters_enabled']);
        $this->assertInstanceOf(Hm_Test_Sieve_Client::class, $res->handler_response['sieve_filters_client']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_save_filter_adds_success_message() {
        $test = new Sieve_Handler_Test('sieve_save_filter', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'sieve_filter_name' => 'Important Sender',
            'sieve_filter_priority' => '10',
            'current_editing_filter_name' => '',
            'conditions_json' => json_encode(array((object) array(
                'condition' => 'from',
                'type' => 'Contains',
                'value' => 'camilux@example.com',
            ))),
            'actions_json' => json_encode(array((object) array(
                'action' => 'keep',
                'value' => '',
                'extra_option_value' => '',
            ))),
            'filter_test_type' => 'ALLOF',
            'filter_source' => 'message_list',
            'gen_script' => false,
        );
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $test->run();

        $this->assertEquals(array('Filter saved'), Hm_Msgs::get());
        $this->assertEquals('main_script', Hm_Test_Sieve_Client::$activated);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_save_filter_gen_script_returns_script_details_without_persisting_script() {
        $test = new Sieve_Handler_Test('sieve_save_filter', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'sieve_filter_name' => 'Important Sender',
            'sieve_filter_priority' => '10',
            'current_editing_filter_name' => '',
            'conditions_json' => json_encode(array((object) array(
                'condition' => 'from',
                'type' => 'Contains',
                'value' => 'camilux@example.com',
            ))),
            'actions_json' => json_encode(array((object) array(
                'action' => 'keep',
                'value' => '',
                'extra_option_value' => '',
            ))),
            'filter_test_type' => 'ALLOF',
            'filter_source' => 'message_list',
            'gen_script' => true,
        );
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertArrayHasKey('script_details', $res->handler_response);
        $this->assertEquals('Important Sender', $res->handler_response['script_details']['filter_name']);
        $this->assertEquals('10', $res->handler_response['script_details']['filter_priority']);
        $this->assertStringContainsString('keep;', $res->handler_response['script_details']['gen_script']);
        $this->assertSame(array(), Hm_Test_Sieve_Client::$scripts);
        $this->assertSame(array(), Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_save_script_adds_success_message() {
        $test = new Sieve_Handler_Test('sieve_save_script', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'sieve_script_name' => 'Manual Script',
            'sieve_script_priority' => '15',
            'current_editing_script' => '',
            'script' => "require [\"fileinto\"];\nkeep;",
        );
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $test->run();

        $this->assertEquals(array('Script saved'), Hm_Msgs::get());
        $this->assertArrayHasKey('manual_script-15-cypht', Hm_Test_Sieve_Client::$scripts);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_toggle_script_state_renames_script_and_rebuilds_main_script() {
        Hm_Test_Sieve_Client::$scripts = array(
            'main_script' => 'require ["include"];',
            'manual_script-15-cypht' => "require [\"fileinto\"];\nkeep;",
        );
        Hm_IMAP_List::init(new Hm_Mock_Config(), new Hm_Mock_Session());
        Hm_IMAP_List::add(array(
            'name' => 'Primary Account',
            'server' => 'imap.example.com',
            'user' => 'user@example.com',
            'pass' => 'secret',
            'sieve_config_host' => 'tls://sieve.example.com:4190',
            'id' => 0,
        ));

        $test = new Sieve_Handler_Test('sieve_toggle_script_state', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_account' => 0,
            'script_state' => 0,
            'sieve_script_name' => 'manual_script-15-cypht',
        );
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertTrue($res->handler_response['success']);
        $this->assertArrayHasKey('sdisabled_manual_script-15-cypht', Hm_Test_Sieve_Client::$scripts);
        $this->assertEquals(array(array('manual_script-15-cypht', 'sdisabled_manual_script-15-cypht')), Hm_Test_Sieve_Client::$renamed);
        $this->assertEquals(array('Script disabled'), Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_save_filter_adds_error_message_when_factory_fails() {
        $test = new Sieve_Handler_Test('sieve_save_filter', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Failing_Factory');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'sieve_filter_name' => 'Important Sender',
            'sieve_filter_priority' => '10',
            'current_editing_filter_name' => '',
            'conditions_json' => json_encode(array((object) array(
                'condition' => 'from',
                'type' => 'Contains',
                'value' => 'camilux@example.com',
            ))),
            'actions_json' => json_encode(array((object) array(
                'action' => 'keep',
                'value' => '',
                'extra_option_value' => '',
            ))),
            'filter_test_type' => 'ALLOF',
            'filter_source' => 'message_list',
            'gen_script' => false,
        );
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $test->run();

        $this->assertEquals(array('Sieve: Test failure'), Hm_Msgs::get());
    }

    private function blockedSendersScript(array $senders, array $actions = []) {
        $lines = array(
            "# CYPHT CONFIG HEADER - DON'T REMOVE",
            '# ' . base64_encode(json_encode($senders)),
        );
        if (!empty($actions)) {
            $lines[] = '# ' . base64_encode(json_encode($actions));
        }
        $lines[] = '';
        $lines[] = 'discard; stop;';
        return implode("\n", $lines);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_filters_enabled_outputs_user_config_value() {
        $test = new Sieve_Handler_Test('sieve_filters_enabled', 'sievefilters');
        $test->user_config = array('enable_sieve_filter_setting' => true);
        $res = $test->run();
        $this->assertTrue($res->handler_response['sieve_filters_enabled']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_block_change_behaviour_updates_user_config_and_adds_message() {
        $test = new Sieve_Handler_Test('sieve_block_change_behaviour_script', 'sievefilters');
        $test->post = array(
            'imap_server_id' => 'serverA',
            'selected_behaviour' => 'Reject',
            'reject_message' => 'No spam allowed',
        );
        $test->user_config = array('enable_sieve_filter_setting' => true);
        $test->run();
        $this->assertEquals(array('Default behaviour changed'), Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_load_imap_outputs_accounts_site_config_and_user_config() {
        $test = new Sieve_Handler_Test('settings_load_imap', 'sievefilters');
        $test->user_config = array('imap_servers' => $this->imapServersConfig());
        $res = $test->run();
        $this->assertEquals($this->imapServersConfig(), $res->handler_response['imap_accounts']);
        $this->assertNotNull($res->handler_response['site_config']);
        $this->assertNotNull($res->handler_response['user_config']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_behaviour_outputs_empty_arrays_when_not_configured() {
        $test = new Sieve_Handler_Test('load_behaviour', 'sievefilters');
        $test->user_config = array('enable_sieve_filter_setting' => true);
        $res = $test->run();
        $this->assertEquals(array(), $res->handler_response['sieve_block_default_behaviour']);
        $this->assertEquals(array(), $res->handler_response['sieve_block_default_reject_message']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_behaviour_outputs_configured_values() {
        $test = new Sieve_Handler_Test('load_behaviour', 'sievefilters');
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'sieve_block_default_behaviour' => array('serverA' => 'Reject'),
            'sieve_block_default_reject_message' => array('serverA' => 'Blocked'),
        );
        $res = $test->run();
        $this->assertEquals(array('serverA' => 'Reject'), $res->handler_response['sieve_block_default_behaviour']);
        $this->assertEquals(array('serverA' => 'Blocked'), $res->handler_response['sieve_block_default_reject_message']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_unblock_sender_removes_sender_and_adds_message() {
        Hm_Test_Sieve_Client::$scripts = array(
            'main_script' => 'require ["include"];',
            'blocked_senders' => $this->blockedSendersScript(array('spam@example.com')),
        );
        $test = new Sieve_Handler_Test('sieve_unblock_sender', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array('imap_server_id' => 'serverA', 'sender' => 'spam@example.com');
        $test->user_config = array('imap_servers' => $this->imapServersConfig(), 'enable_sieve_filter_setting' => true);
        $test->run();
        $this->assertEquals(array('Sender Unblocked'), Hm_Msgs::get());
        $this->assertEquals('', Hm_Test_Sieve_Client::$scripts['blocked_senders']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_block_domain_blocks_wildcard_domain_and_sets_reload_page() {
        Hm_Test_Sieve_Client::$scripts = array('main_script' => 'require ["include"];');
        $test = new Sieve_Handler_Test('sieve_block_domain_script', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array('imap_server_id' => 'serverA', 'sender' => 'spam@example.com');
        $test->user_config = array('imap_servers' => $this->imapServersConfig(), 'enable_sieve_filter_setting' => true);
        $res = $test->run();
        $this->assertTrue($res->handler_response['reload_page']);
        $this->assertArrayHasKey('blocked_senders', Hm_Test_Sieve_Client::$scripts);
        $this->assertStringContainsString('@example.com', Hm_Test_Sieve_Client::$scripts['blocked_senders']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_block_unblock_script_blocks_new_sender_with_discard_action() {
        Hm_Test_Sieve_Client::$scripts = array();
        $test = new Sieve_Handler_Test('sieve_block_unblock_script', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_server_id' => 'serverA',
            'block_action' => 'discard',
            'scope' => 'sender',
            'sender' => 'spammer@example.com',
            'reject_message' => '',
        );
        $test->user_config = array('imap_servers' => $this->imapServersConfig(), 'enable_sieve_filter_setting' => true);
        $test->run();
        $this->assertEquals(array('Sender Blocked'), Hm_Msgs::get());
        $this->assertArrayHasKey('blocked_senders', Hm_Test_Sieve_Client::$scripts);
        $this->assertStringContainsString('spammer@example.com', Hm_Test_Sieve_Client::$scripts['blocked_senders']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_block_unblock_script_unblocks_existing_sender() {
        $senders = array('existing@example.com');
        $actions = array('existing@example.com' => array('action' => 'discard', 'reject_message' => ''));
        Hm_Test_Sieve_Client::$scripts = array(
            'blocked_senders' => $this->blockedSendersScript($senders, $actions),
        );
        $test = new Sieve_Handler_Test('sieve_block_unblock_script', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_server_id' => 'serverA',
            'block_action' => 'discard',
            'scope' => 'sender',
            'sender' => 'existing@example.com',
            'reject_message' => '',
        );
        $test->user_config = array('imap_servers' => $this->imapServersConfig(), 'enable_sieve_filter_setting' => true);
        $test->run();
        $this->assertEquals(array('Sender Unblocked'), Hm_Msgs::get());
        $this->assertEquals('', Hm_Test_Sieve_Client::$scripts['blocked_senders']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_list_block_sieve_script_outputs_json_of_blocked_senders() {
        Hm_Test_Sieve_Client::$scripts = array(
            'blocked_senders' => $this->blockedSendersScript(array('blocked@example.com')),
        );
        Hm_IMAP_List::init(new Hm_Mock_Config(), new Hm_Mock_Session());
        Hm_IMAP_List::add(array(
            'name' => 'Primary Account',
            'server' => 'imap.example.com',
            'user' => 'user@example.com',
            'pass' => 'secret',
            'sieve_config_host' => 'tls://sieve.example.com:4190',
            'id' => 0,
        ));
        $test = new Sieve_Handler_Test('list_block_sieve_script', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array('imap_server_id' => 0);
        $test->user_config = array('imap_servers' => $this->imapServersConfig(), 'enable_sieve_filter_setting' => true);
        $res = $test->run();
        $this->assertEquals(json_encode(array('blocked@example.com')), $res->handler_response['ajax_list_block_sieve']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_account_sieve_filters_outputs_mailbox_with_extensions() {
        Hm_Test_Sieve_Client::$scripts = array();
        $test = new Sieve_Handler_Test('load_account_sieve_filters', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array('imap_server_id' => 'serverA');
        $test->input = array('imap_accounts' => $this->imapServersConfig());
        $test->user_config = array('imap_servers' => $this->imapServersConfig(), 'enable_sieve_filter_setting' => true);
        $res = $test->run();
        $this->assertArrayHasKey('mailbox', $res->handler_response);
        $this->assertEquals(array('fileinto', 'reject'), $res->handler_response['mailbox']['sieve_extensions']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_toggle_script_state_enables_disabled_script() {
        Hm_Test_Sieve_Client::$scripts = array(
            'main_script' => 'require ["include"];',
            'sdisabled_manual_script-15-cypht' => "require [\"fileinto\"];\nkeep;",
        );
        Hm_IMAP_List::init(new Hm_Mock_Config(), new Hm_Mock_Session());
        Hm_IMAP_List::add(array(
            'name' => 'Primary Account',
            'server' => 'imap.example.com',
            'user' => 'user@example.com',
            'pass' => 'secret',
            'sieve_config_host' => 'tls://sieve.example.com:4190',
            'id' => 0,
        ));
        $test = new Sieve_Handler_Test('sieve_toggle_script_state', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->post = array(
            'imap_account' => 0,
            'script_state' => 1,
            'sieve_script_name' => 'sdisabled_manual_script-15-cypht',
        );
        $test->user_config = array('imap_servers' => $this->imapServersConfig(), 'enable_sieve_filter_setting' => true);
        $res = $test->run();
        $this->assertTrue($res->handler_response['success']);
        $this->assertArrayHasKey('senabled_sdisabled_manual_script-15-cypht', Hm_Test_Sieve_Client::$scripts);
        $this->assertEquals(array('Script enabled'), Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_save_script_adds_error_message_when_factory_fails() {
        $test = new Sieve_Handler_Test('sieve_save_script', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Failing_Factory');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'sieve_script_name' => 'Manual Script',
            'sieve_script_priority' => '15',
            'current_editing_script' => '',
            'script' => "require [\"fileinto\"];\nkeep;",
        );
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $test->run();

        $this->assertEquals(array('Sieve: Test failure'), Hm_Msgs::get());
    }

    /* ------------------------------------------------------------------ *
     * process_enable_sieve_filter_setting - the switch every handler above
     * checks with should_skip_execution()
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_enable_sieve_filter_setting_saves_checked_box() {
        $test = new Sieve_Handler_Test('process_enable_sieve_filter_setting', 'sievefilters');
        $test->post = array('save_settings' => 1, 'enable_sieve_filter' => 1);

        $res = $test->run();

        $this->assertEquals(1, $res->handler_response['new_user_settings']['enable_sieve_filter_setting']);
    }

    /**
     * An unchecked box is absent from the POST entirely, which has to be read
     * as "off" rather than "unchanged".
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_enable_sieve_filter_setting_saves_unchecked_box_as_false() {
        $test = new Sieve_Handler_Test('process_enable_sieve_filter_setting', 'sievefilters');
        $test->post = array('save_settings' => 1);

        $res = $test->run();

        $this->assertFalse($res->handler_response['new_user_settings']['enable_sieve_filter_setting']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_process_enable_sieve_filter_setting_reads_stored_value_when_not_saving() {
        $test = new Sieve_Handler_Test('process_enable_sieve_filter_setting', 'sievefilters');
        $test->post = array();
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $this->assertTrue($res->handler_response['user_settings']['enable_sieve_filter']);
        $this->assertSame(array(), $res->handler_response['new_user_settings']);
    }

}
