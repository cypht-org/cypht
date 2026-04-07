<?php

use PHPUnit\Framework\TestCase;

class Sieve_Handler_Test {
    public $post = array();
    public $get = array();
    public $user_config = array();
    public $config = array();
    public $input = array();
    public $modules = array();
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
        Hm_Server_Wrapper::init($this->module_exec->user_config, $this->ses_obj);
    }

    public function run() {
        $this->prep();
        $this->module_exec->run_handler_modules($this->req_obj, $this->ses_obj, 'test');
        return $this->module_exec;
    }
}

class Hm_Test_Sieve_Client {
    public static $scripts = array();
    public static $activated = '';
    public static $renamed = array();

    public function listScripts() {
        return array_keys(self::$scripts);
    }

    public function getScript($name) {
        return self::$scripts[$name] ?? '';
    }

    public function putScript($name, $script) {
        self::$scripts[$name] = $script;
        return true;
    }

    public function removeScripts($name) {
        unset(self::$scripts[$name]);
        return true;
    }

    public function activateScript($name) {
        self::$activated = $name;
        return true;
    }

    public function renameScript($name, $prefix) {
        $new_name = $prefix.$name;
        self::$scripts[$new_name] = self::$scripts[$name] ?? '';
        unset(self::$scripts[$name]);
        self::$renamed[] = array($name, $new_name);
        return true;
    }

    public function close() {
        return true;
    }

    public function getErrorMessage() {
        return '';
    }
}

class Hm_Test_Sieve_Client_Factory {
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false)
    {
        return new Hm_Test_Sieve_Client();
    }
}

class Hm_Test_Sieve_Client_Failing_Factory {
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false)
    {
        throw new Exception('Test failure');
    }
}

class Hm_Test_Sievefilters_Handler_Modules extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'modules/sievefilters/modules.php';
        Hm_Test_Sieve_Client::$scripts = array();
        Hm_Test_Sieve_Client::$activated = '';
        Hm_Test_Sieve_Client::$renamed = array();
        Hm_Msgs::flush();
    }

    private function sieveScriptWithSource($source) {
        return implode("\n", array(
            "# CYPHT CONFIG HEADER - DON'T REMOVE",
            '# '.base64_encode(json_encode(array(array('condition' => 'from', 'type' => 'Contains', 'value' => 'sender@example.com')))),
            '# '.base64_encode(json_encode(array(array('action' => 'keep', 'value' => '')))),
            '# '.base64_encode($source),
            '',
            'require ["fileinto"];',
        ));
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
            array(
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
    public function test_new_filter_message_dropdown_contains_create_button_and_header_toggles() {
        $parent = build_parent_mock();
        $mod = new Hm_Output_new_sieve_filter_for_message_like_this($parent, 'test');
        $mod->output_data = array(
            'mailbox_name' => 'Primary Account',
            'filter_headers' => array(
                'from' => 'sender@example.com',
                'to' => 'team@example.com',
                'subject' => 'Build update',
                'reply-to' => 'reply@example.com',
            ),
        );

        $mod->output();
        $content = $mod->output_data['new_filter'];

        $this->assertStringContainsString('id="filter_message"', $content);
        $this->assertStringContainsString('id="use_from" checked', $content);
        $this->assertStringContainsString('id="use_to"', $content);
        $this->assertStringContainsString('id="use_subject"', $content);
        $this->assertStringContainsString('id="use_reply"', $content);
        $this->assertStringContainsString('id="create_filter"', $content);
        $this->assertStringContainsString('Create filter', $content);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_mailbox_name_from_list_path() {
        $test = new Sieve_Handler_Test('load_mailbox_name', 'sievefilters');
        $test->get = array('list_path' => 'imap_0_INBOX');
        $test->user_config = array(
            'imap_servers' => array(
                array('name' => 'Primary Account', 'sieve_config_host' => 'tls://sieve.example.com:4190'),
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
    public function test_load_custom_actions_returns_all_cypht_filters() {
        Hm_Test_Sieve_Client::$scripts = array(
            'from_list-10-cyphtfilter' => $this->sieveScriptWithSource('message_list'),
            'from_message-20-cyphtfilter' => $this->sieveScriptWithSource('message'),
            'manual_script-30-cypht' => "require [\"fileinto\"];",
        );

        $test = new Sieve_Handler_Test('load_custom_actions', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->get = array('list_path' => 'imap_0_INBOX');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();
        $actions = $res->handler_response['custom_actions'];

        $this->assertCount(2, $actions);
        $this->assertEquals('from_list-10-cyphtfilter', $actions[0]['id']);
        $this->assertEquals('from message', $actions[1]['name']);
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
        $test->post = array('imap_server_id' => 0);
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
}
