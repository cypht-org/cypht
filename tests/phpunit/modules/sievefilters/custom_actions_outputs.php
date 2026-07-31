<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/sieve_test_helpers.php';

/**
 * Output modules for custom action buttons: the manually triggered, per-account
 * buttons. Kept apart from the automatic (sieve) actions below, which render a
 * separate dropdown driven by server-side filter scripts.
 */
class Hm_Test_Sievefilters_Custom_Actions_Outputs extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'modules/sievefilters/modules.php';
        Hm_Test_Sieve_Output_Client::$scripts = array();
        Hm_Msgs::flush();
    }

    /**
     * Two saved actions for one account, in the shape the handlers emit.
     */
    private function customActions() {
        return array(
            'ca_move' => array(
                'id' => 'ca_move',
                'name' => 'Move Important',
                'actions' => array(array('action' => 'move', 'value' => 'Important')),
            ),
            'ca_flag' => array(
                'id' => 'ca_flag',
                'name' => 'Flag Message',
                'actions' => array(array('action' => 'flag', 'value' => 'flagged')),
            ),
        );
    }

    private function siteConfigWithSieveFactory() {
        $config = new Hm_Mock_Config();
        $config->set('sieve_client_factory', 'Hm_Test_Sieve_Output_Client_Factory');
        return $config;
    }

    /* ------------------------------------------------------------------ *
     * message list toolbar dropdown
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_custom_actions_renders_a_button_per_action() {
        $test = new Sieve_Output_Test('message_list_custom_actions', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => true,
            'mailbox_name' => 'Primary Account',
            'custom_actions' => $this->customActions(),
        );

        $res = $test->run();
        $html = $res->output_response['msg_controls_custom_actions'];

        $this->assertStringContainsString('id="filter_message"', $html);
        $this->assertStringContainsString('data-action-id="ca_move"', $html);
        $this->assertStringContainsString('data-action-name="Move Important"', $html);
        $this->assertStringContainsString('data-action-id="ca_flag"', $html);
        $this->assertStringContainsString('data-imap-account="Primary Account"', $html);
        $this->assertStringContainsString('id="add_custom_action_button"', $html);
        $this->assertStringContainsString('account="Primary Account"', $html);
        // list variant only; the message page markers must not appear
        $this->assertStringNotContainsString('custom_action_btn_message', $html);
        $this->assertStringNotContainsString('data-msg-uid=', $html);
    }

    /**
     * With nothing saved yet the dropdown still renders so the user has a way
     * to create their first action.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_custom_actions_with_no_actions_only_shows_create_button() {
        $test = new Sieve_Output_Test('message_list_custom_actions', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => true,
            'mailbox_name' => 'Primary Account',
            'custom_actions' => array(),
        );

        $res = $test->run();
        $html = $res->output_response['msg_controls_custom_actions'];

        $this->assertStringContainsString('id="add_custom_action_button"', $html);
        $this->assertStringNotContainsString('data-action-id=', $html);
        $this->assertStringNotContainsString('dropdown-divider', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_custom_actions_renders_nothing_when_sieve_disabled() {
        $test = new Sieve_Output_Test('message_list_custom_actions', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => false,
            'mailbox_name' => 'Primary Account',
            'custom_actions' => $this->customActions(),
        );

        $res = $test->run();

        $this->assertArrayNotHasKey('msg_controls_custom_actions', $res->output_response);
    }

    /**
     * Action names come from user input and are echoed into attributes.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_custom_actions_escapes_action_names() {
        $test = new Sieve_Output_Test('message_list_custom_actions', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => true,
            'mailbox_name' => 'Primary Account',
            'custom_actions' => array(
                'ca_xss' => array(
                    'id' => 'ca_xss',
                    'name' => '"><img src=x onerror=alert(1)>',
                    'actions' => array(array('action' => 'keep', 'value' => '')),
                ),
            ),
        );

        $res = $test->run();
        $html = $res->output_response['msg_controls_custom_actions'];

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    /* ------------------------------------------------------------------ *
     * message page dropdown
     * ------------------------------------------------------------------ */

    /**
     * The message page variant has no checkbox selection, so every button has
     * to carry the open message's coordinates.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_page_custom_actions_carries_the_open_message_target() {
        $test = new Sieve_Output_Test('message_page_custom_actions', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => true,
            'mailbox_name' => 'Primary Account',
            'custom_actions' => $this->customActions(),
            'msg_server_id' => 'serverA',
            'msg_text_uid' => '42',
            'msg_folder' => 'SU5CT1g=',
        );

        $res = $test->run();
        $html = $res->output_response['message_custom_actions'];

        $this->assertStringContainsString('id="message_custom_actions_toggle"', $html);
        $this->assertStringContainsString('custom-actions-message', $html);
        $this->assertStringContainsString('custom_action_btn_message', $html);
        $this->assertStringContainsString('data-msg-server-id="serverA"', $html);
        $this->assertStringContainsString('data-msg-uid="42"', $html);
        $this->assertStringContainsString('data-msg-folder="SU5CT1g="', $html);
        $this->assertStringContainsString('add_custom_action_message', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_page_custom_actions_renders_nothing_when_sieve_disabled() {
        $test = new Sieve_Output_Test('message_page_custom_actions', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => false,
            'mailbox_name' => 'Primary Account',
            'custom_actions' => $this->customActions(),
        );

        $res = $test->run();

        $this->assertArrayNotHasKey('message_custom_actions', $res->output_response);
    }

    /* ------------------------------------------------------------------ *
     * ajax pass-through outputs
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_output_passes_through_id_and_error() {
        $test = new Sieve_Output_Test('save_custom_action', 'sievefilters');
        $test->handler_response = array(
            'custom_action_saved' => true,
            'custom_action_id' => 'ca_new',
        );

        $res = $test->run();

        $this->assertTrue($res->output_response['custom_action_saved']);
        $this->assertEquals('ca_new', $res->output_response['custom_action_id']);
        $this->assertArrayNotHasKey('custom_action_error', $res->output_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_output_defaults_when_handler_did_not_run() {
        $test = new Sieve_Output_Test('save_custom_action', 'sievefilters');
        $test->handler_response = array('custom_action_error' => 'Missing account');

        $res = $test->run();

        $this->assertFalse($res->output_response['custom_action_saved']);
        $this->assertEquals('', $res->output_response['custom_action_id']);
        $this->assertEquals('Missing account', $res->output_response['custom_action_error']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_apply_custom_action_output_reports_success_and_count() {
        $test = new Sieve_Output_Test('apply_custom_action', 'sievefilters');
        $test->handler_response = array('apply_success' => true, 'apply_count' => 3);

        $res = $test->run();

        $this->assertTrue($res->output_response['apply_success']);
        $this->assertEquals(3, $res->output_response['apply_count']);
        $this->assertArrayNotHasKey('custom_action_error', $res->output_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_apply_custom_action_output_reports_failure() {
        $test = new Sieve_Output_Test('apply_custom_action', 'sievefilters');
        $test->handler_response = array('custom_action_error' => 'Could not connect to server');

        $res = $test->run();

        $this->assertFalse($res->output_response['apply_success']);
        $this->assertEquals(0, $res->output_response['apply_count']);
        $this->assertEquals('Could not connect to server', $res->output_response['custom_action_error']);
    }

    /**
     * The JS compares this against the string '1' before removing the row.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_custom_action_output_passes_through_result() {
        $test = new Sieve_Output_Test('delete_custom_action', 'sievefilters');
        $test->handler_response = array('custom_action_deleted' => 1);

        $res = $test->run();

        $this->assertEquals(1, $res->output_response['custom_action_deleted']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_custom_action_output_defaults_to_zero() {
        $test = new Sieve_Output_Test('delete_custom_action', 'sievefilters');

        $res = $test->run();

        $this->assertEquals(0, $res->output_response['custom_action_deleted']);
    }

    /* ------------------------------------------------------------------ *
     * modal template
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_custom_action_modal_content_provides_name_input_and_action_rows() {
        $test = new Sieve_Output_Test('custom_action_modal_content', 'sievefilters');

        $res = $test->run();
        $html = $res->output_response[0];

        $this->assertStringContainsString('id="custom_action_template"', $html);
        $this->assertStringContainsString('custom_action_name_input', $html);
        $this->assertStringContainsString('filter_modal_add_action_btn', $html);
        // hidden until the modal is opened
        $this->assertStringContainsString('class="d-none"', $html);
    }

    /* ------------------------------------------------------------------ *
     * the custom actions tab on the account settings screen
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_account_sieve_filters_lists_custom_actions_in_their_own_tab() {
        $test = new Sieve_Output_Test('account_sieve_filters', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => true,
            'site_config' => $this->siteConfigWithSieveFactory(),
            'user_config' => new Hm_Mock_Config(),
            'mailbox' => array(
                'name' => 'Primary Account',
                'sieve_config_host' => 'tls://sieve.example.com:4190',
                'sieve_extensions' => array('fileinto', 'reject'),
            ),
            'account_custom_actions' => $this->customActions(),
        );

        $res = $test->run();
        $html = $res->output_response['sieve_detail_display'];

        $this->assertStringContainsString('data-tab="custom-actions"', $html);
        $this->assertStringContainsString('(2)', $html);
        $this->assertStringContainsString('custom_actions_details', $html);
        $this->assertStringContainsString('Move Important', $html);
        $this->assertStringContainsString('class="edit_custom_action ps-2" data-action-id="ca_move"', $html);
        $this->assertStringContainsString('class="delete_custom_action ps-2" data-action-id="ca_flag"', $html);
        $this->assertStringContainsString('create_custom_action', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_account_sieve_filters_shows_empty_state_without_custom_actions() {
        $test = new Sieve_Output_Test('account_sieve_filters', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => true,
            'site_config' => $this->siteConfigWithSieveFactory(),
            'user_config' => new Hm_Mock_Config(),
            'mailbox' => array(
                'name' => 'Primary Account',
                'sieve_config_host' => 'tls://sieve.example.com:4190',
                'sieve_extensions' => array(),
            ),
            'account_custom_actions' => array(),
        );

        $res = $test->run();
        $html = $res->output_response['sieve_detail_display'];

        $this->assertStringContainsString('(0)', $html);
        $this->assertStringNotContainsString('custom_actions_details', $html);
        $this->assertStringContainsString('No custom actions defined', $html);
    }

    /* ------------------------------------------------------------------ *
     * render_custom_actions_dropdown - the shared renderer behind both
     * dropdowns above; these pin down what the two variants must not share
     * ------------------------------------------------------------------ */

    private function messageContext() {
        return array(
            'server_id' => 'serverA',
            'uid' => '42',
            'folder' => 'SU5CT1g=',
        );
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_render_custom_actions_dropdown_list_variant_renders_one_button_per_action() {
        $html = render_custom_actions_dropdown(
            new Hm_Test_Sieve_Output_Trans_Stub(),
            $this->customActions(),
            'Primary Account'
        );

        $this->assertStringContainsString('id="filter_message"', $html);
        $this->assertStringContainsString('class="custom_action_btn btn btn-sm btn-outline-secondary text-start"', $html);
        $this->assertStringContainsString('data-action-id="ca_move"', $html);
        $this->assertStringContainsString('data-action-name="Move Important"', $html);
        $this->assertStringContainsString('data-action-id="ca_flag"', $html);
        $this->assertStringContainsString('data-action-name="Flag Message"', $html);
        $this->assertStringContainsString('data-imap-account="Primary Account"', $html);
        $this->assertStringContainsString('Create from Selected', $html);
        $this->assertStringContainsString('id="add_custom_action_button"', $html);
        $this->assertStringContainsString('account="Primary Account"', $html);
    }

    /**
     * The list variant is driven by checkbox selection, so it must not emit any
     * per-message targeting attributes or the message-page class hooks.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_render_custom_actions_dropdown_list_variant_has_no_message_context() {
        $html = render_custom_actions_dropdown(
            new Hm_Test_Sieve_Output_Trans_Stub(),
            $this->customActions(),
            'Primary Account'
        );

        $this->assertStringNotContainsString('custom_action_btn_message', $html);
        $this->assertStringNotContainsString('add_custom_action_message', $html);
        $this->assertStringNotContainsString('custom-actions-message', $html);
        $this->assertStringNotContainsString('data-msg-server-id', $html);
        $this->assertStringNotContainsString('data-msg-uid', $html);
        $this->assertStringNotContainsString('data-msg-folder', $html);
        $this->assertStringNotContainsString('d-inline-block', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_render_custom_actions_dropdown_message_variant_targets_the_open_message() {
        $html = render_custom_actions_dropdown(
            new Hm_Test_Sieve_Output_Trans_Stub(),
            $this->customActions(),
            'Primary Account',
            $this->messageContext()
        );

        $this->assertStringContainsString('id="message_custom_actions_toggle"', $html);
        $this->assertStringContainsString('custom-actions-message', $html);
        $this->assertStringContainsString('d-inline-block', $html);
        $this->assertStringContainsString('custom_action_btn_message', $html);
        $this->assertStringContainsString('data-msg-server-id="serverA"', $html);
        $this->assertStringContainsString('data-msg-uid="42"', $html);
        $this->assertStringContainsString('data-msg-folder="SU5CT1g="', $html);
        $this->assertStringNotContainsString('id="filter_message"', $html);
    }

    /**
     * On the message page there is no selection to build from, so the create
     * button is scoped to the open message and relabelled.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_render_custom_actions_dropdown_message_variant_relabels_create_button() {
        $html = render_custom_actions_dropdown(
            new Hm_Test_Sieve_Output_Trans_Stub(),
            array(),
            'Primary Account',
            $this->messageContext()
        );

        $this->assertStringContainsString('add_custom_action_message', $html);
        $this->assertStringContainsString('Create for message like this', $html);
        $this->assertStringNotContainsString('Create from Selected', $html);
        // even with no saved actions the create button keeps the message target
        $this->assertStringContainsString('data-msg-uid="42"', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_render_custom_actions_dropdown_without_actions_omits_the_button_list() {
        $html = render_custom_actions_dropdown(
            new Hm_Test_Sieve_Output_Trans_Stub(),
            array(),
            'Primary Account'
        );

        $this->assertStringNotContainsString('data-action-id=', $html);
        $this->assertStringNotContainsString('dropdown-divider', $html);
        $this->assertStringContainsString('id="add_custom_action_button"', $html);
        $this->assertStringContainsString('Customised actions you can apply to selected emails', $html);
    }

    /**
     * Action names and ids are user supplied and land inside HTML attributes.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_render_custom_actions_dropdown_escapes_action_name_and_id() {
        $html = render_custom_actions_dropdown(
            new Hm_Test_Sieve_Output_Trans_Stub(),
            array(
                'ca_xss' => array(
                    'id' => 'ca"onmouseover="alert(1)',
                    'name' => '<b>bold</b>',
                ),
            ),
            'Primary Account'
        );

        // the raw attribute break must not survive; only its escaped form may
        $this->assertStringNotContainsString('ca"onmouseover', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
        $this->assertStringContainsString('data-action-id="ca&quot;onmouseover=&quot;alert(1)"', $html);
    }

    /**
     * The message context values are escaped too, since folder names arrive
     * hex/base64 encoded but the uid and server id come off the page.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_render_custom_actions_dropdown_escapes_message_context() {
        $html = render_custom_actions_dropdown(
            new Hm_Test_Sieve_Output_Trans_Stub(),
            $this->customActions(),
            'Primary Account',
            array('server_id' => 'a"b', 'uid' => '1', 'folder' => 'x')
        );

        $this->assertStringContainsString('data-msg-server-id="a&quot;b"', $html);
        $this->assertStringNotContainsString('data-msg-server-id="a"b"', $html);
    }

    /**
     * Both variants keep the same outer dropdown contract so the shared JS
     * handler can find them.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_render_custom_actions_dropdown_variants_share_the_dropdown_contract() {
        $list = render_custom_actions_dropdown(
            new Hm_Test_Sieve_Output_Trans_Stub(),
            $this->customActions(),
            'Primary Account'
        );
        $message = render_custom_actions_dropdown(
            new Hm_Test_Sieve_Output_Trans_Stub(),
            $this->customActions(),
            'Primary Account',
            $this->messageContext()
        );

        foreach (array($list, $message) as $html) {
            $this->assertStringContainsString('data-bs-toggle="dropdown"', $html);
            $this->assertStringContainsString('dropdown-menu custom-actions', $html);
            $this->assertStringContainsString('Custom actions', $html);
            $this->assertStringContainsString('add_custom_action', $html);
            $this->assertStringContainsString('dropdown-divider', $html);
        }
    }
}
