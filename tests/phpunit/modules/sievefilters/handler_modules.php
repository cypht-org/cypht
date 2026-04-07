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