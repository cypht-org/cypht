<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/sieve_test_helpers.php';

/**
 * Automatic actions: the cypht-managed sieve filter scripts surfaced in the
 * message list toolbar. These run server side on incoming mail, so unlike
 * custom actions they are backed by scripts on the sieve host rather than by
 * user_config.
 */
class Hm_Test_Sievefilters_Automatic_Actions_Handlers extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'modules/sievefilters/modules.php';
        Hm_Test_Sieve_Client::$scripts = array();
        Hm_Msgs::flush();
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

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_automatic_actions_returns_only_cypht_filter_scripts() {
        Hm_Test_Sieve_Client::$scripts = array(
            'from_list-10-cyphtfilter' => $this->sieveScriptWithSource('message_list'),
            'from_message-20-cyphtfilter' => $this->sieveScriptWithSource('message'),
            'manual_script-30-cypht' => "require [\"fileinto\"];",
        );

        $test = new Sieve_Handler_Test('load_automatic_actions', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->get = array('list_path' => 'imap_serverA_INBOX');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();
        $filters = $res->handler_response['automatic_actions'];

        $this->assertCount(2, $filters);
        $this->assertEquals('from_list-10-cyphtfilter', $filters[0]['id']);
        $this->assertEquals('from list', $filters[0]['name']);
        $this->assertEquals('message_list', $filters[0]['source']);
        $this->assertEquals('from message', $filters[1]['name']);
        $this->assertEquals('message', $filters[1]['source']);
    }

    /**
     * Disabled scripts carry an sdisabled_ prefix that must be stripped for display.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_automatic_actions_strips_enabled_disabled_prefix_from_name() {
        Hm_Test_Sieve_Client::$scripts = array(
            'sdisabled_paused_filter-10-cyphtfilter' => $this->sieveScriptWithSource('message_list'),
        );

        $test = new Sieve_Handler_Test('load_automatic_actions', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->get = array('list_path' => 'imap_serverA_INBOX');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertEquals('paused filter', $res->handler_response['automatic_actions'][0]['name']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_automatic_actions_returns_empty_without_list_path() {
        $test = new Sieve_Handler_Test('load_automatic_actions', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertSame(array(), $res->handler_response['automatic_actions']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_automatic_actions_returns_empty_for_unknown_server() {
        $test = new Sieve_Handler_Test('load_automatic_actions', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->get = array('list_path' => 'imap_serverZ_INBOX');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertSame(array(), $res->handler_response['automatic_actions']);
    }

    /**
     * An account without a sieve host must short-circuit instead of trying to connect.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_automatic_actions_returns_empty_when_account_has_no_sieve_host() {
        $test = new Sieve_Handler_Test('load_automatic_actions', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Failing_Factory');
        $test->get = array('list_path' => 'imap_serverA_INBOX');
        $test->user_config = array(
            'imap_servers' => array('serverA' => array('name' => 'Primary Account')),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertSame(array(), $res->handler_response['automatic_actions']);
        $this->assertEquals(array(), Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_automatic_actions_adds_error_message_when_factory_fails() {
        $test = new Sieve_Handler_Test('load_automatic_actions', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Failing_Factory');
        $test->get = array('list_path' => 'imap_serverA_INBOX');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
        );

        $res = $test->run();

        $this->assertSame(array(), $res->handler_response['automatic_actions']);
        $this->assertEquals(array('Sieve: Test failure'), Hm_Msgs::get());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_automatic_actions_skips_when_sieve_disabled() {
        $test = new Sieve_Handler_Test('load_automatic_actions', 'sievefilters');
        $test->config = array('sieve_client_factory' => 'Hm_Test_Sieve_Client_Factory');
        $test->get = array('list_path' => 'imap_serverA_INBOX');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => false,
        );

        $res = $test->run();

        $this->assertArrayNotHasKey('automatic_actions', $res->handler_response);
    }
}
