<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/sieve_test_helpers.php';

/**
 * Output modules for sieve filters and scripts themselves: the account settings
 * screen, the misconfiguration alert, and the small ajax pass-throughs. Custom
 * action and automatic action outputs live in their own classes below.
 */
class Hm_Test_Sievefilters_Output_Modules extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'modules/sievefilters/modules.php';
        Hm_Test_Sieve_Output_Client::$scripts = array();
        Hm_Msgs::flush();
    }

    private function siteConfigWithSieveFactory() {
        $config = new Hm_Mock_Config();
        $config->set('sieve_client_factory', 'Hm_Test_Sieve_Output_Client_Factory');
        return $config;
    }

    /* ------------------------------------------------------------------ *
     * account settings screen
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_account_sieve_filters_lists_sieve_scripts_and_filters() {
        Hm_Test_Sieve_Output_Client::$scripts = array(
            'urgent_mail-10-cyphtfilter' => '# filter',
            'manual_script-20-cypht' => '# script',
            'unrelated-30-other' => '# ignored',
        );

        $test = new Sieve_Output_Test('account_sieve_filters', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => true,
            'site_config' => $this->siteConfigWithSieveFactory(),
            'user_config' => new Hm_Mock_Config(),
            'mailbox' => array(
                'name' => 'Primary Account',
                'sieve_config_host' => 'tls://sieve.example.com:4190',
                'sieve_extensions' => array('fileinto'),
            ),
            'account_custom_actions' => array(),
        );

        $res = $test->run();
        $html = $res->output_response['sieve_detail_display'];

        $this->assertStringContainsString('script_name="urgent_mail-10-cyphtfilter"', $html);
        $this->assertStringContainsString('edit_filter', $html);
        $this->assertStringContainsString('script_name="manual_script-20-cypht"', $html);
        $this->assertStringContainsString('edit_script', $html);
        $this->assertStringNotContainsString('unrelated-30-other', $html);
        $this->assertStringContainsString('data-tab="filters"', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_account_sieve_filters_renders_nothing_when_sieve_disabled() {
        $test = new Sieve_Output_Test('account_sieve_filters', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => false,
            'site_config' => $this->siteConfigWithSieveFactory(),
            'user_config' => new Hm_Mock_Config(),
            'mailbox' => array(
                'name' => 'Primary Account',
                'sieve_config_host' => 'tls://sieve.example.com:4190',
                'sieve_extensions' => array(),
            ),
        );

        $res = $test->run();

        $this->assertArrayNotHasKey('sieve_detail_display', $res->output_response);
    }

    /**
     * An account with no sieve host configured cannot show filters at all.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_account_sieve_filters_renders_nothing_without_a_sieve_host() {
        $test = new Sieve_Output_Test('account_sieve_filters', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => true,
            'site_config' => $this->siteConfigWithSieveFactory(),
            'user_config' => new Hm_Mock_Config(),
            'mailbox' => array('name' => 'Primary Account', 'sieve_extensions' => array()),
        );

        $res = $test->run();

        $this->assertArrayNotHasKey('sieve_detail_display', $res->output_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_filter_status_reports_deactivation() {
        $test = new Sieve_Output_Test('check_filter_status', 'sievefilters');
        $test->handler_response = array('sieve_filters_enabled' => false);

        $res = $test->run();

        $this->assertStringContainsString('Sieve filter is deactivated', $res->output_response['sieve_detail_display']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_check_filter_status_is_silent_when_enabled() {
        $test = new Sieve_Output_Test('check_filter_status', 'sievefilters');
        $test->handler_response = array('sieve_filters_enabled' => true);

        $res = $test->run();

        $this->assertArrayNotHasKey('sieve_detail_display', $res->output_response);
    }

    /* ------------------------------------------------------------------ *
     * misconfiguration alert and small pass-through outputs
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_display_sieve_misconfig_alert_shows_the_message() {
        $test = new Sieve_Output_Test('display_sieve_misconfig_alert', 'sievefilters');
        $test->handler_response = array('sieve_alert_message' => 'Sieve is enabled but not fully configured');

        $res = $test->run();
        $html = $res->output_response[0];

        $this->assertStringContainsString('alert-warning', $html);
        $this->assertStringContainsString('Sieve is enabled but not fully configured', $html);
    }

    /**
     * A single server install has nothing to reconcile, so the alert is suppressed.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_display_sieve_misconfig_alert_hidden_in_single_server_mode() {
        $test = new Sieve_Output_Test('display_sieve_misconfig_alert', 'sievefilters');
        $test->handler_response = array(
            'single_server_mode' => true,
            'sieve_alert_message' => 'Sieve is enabled but not fully configured',
        );

        $res = $test->run();

        $this->assertArrayNotHasKey(0, $res->output_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_display_sieve_misconfig_alert_hidden_without_a_message() {
        $test = new Sieve_Output_Test('display_sieve_misconfig_alert', 'sievefilters');

        $res = $test->run();

        $this->assertArrayNotHasKey(0, $res->output_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_edit_filter_output_passes_through_filter_parts() {
        $test = new Sieve_Output_Test('sieve_edit_filter', 'sievefilters');
        $test->handler_response = array(
            'conditions' => '[{"condition":"from"}]',
            'actions' => '[{"action":"keep"}]',
            'test_type' => 'allof',
        );

        $res = $test->run();

        $this->assertEquals('[{"condition":"from"}]', $res->output_response['conditions']);
        $this->assertEquals('[{"action":"keep"}]', $res->output_response['actions']);
        $this->assertEquals('allof', $res->output_response['test_type']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_edit_output_defaults_script_to_empty_string() {
        $test = new Sieve_Output_Test('sieve_edit_output', 'sievefilters');

        $res = $test->run();

        $this->assertEquals('', $res->output_response['script']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_delete_output_passes_through_removal_flag() {
        $test = new Sieve_Output_Test('sieve_delete_output', 'sievefilters');
        $test->handler_response = array('script_removed' => 1);

        $res = $test->run();

        $this->assertEquals(1, $res->output_response['script_removed']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_save_filter_output_passes_through_script_details() {
        $test = new Sieve_Output_Test('sieve_save_filter_output', 'sievefilters');
        $test->handler_response = array('script_details' => array('name' => 'urgent-10-cyphtfilter'));

        $res = $test->run();

        $this->assertEquals(array('name' => 'urgent-10-cyphtfilter'), $res->output_response['script_details']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sieve_get_mailboxes_output_passes_through_folder_json() {
        $test = new Sieve_Output_Test('sieve_get_mailboxes_output', 'sievefilters');
        $test->handler_response = array('mailboxes' => '["INBOX","Archive"]');

        $res = $test->run();

        $this->assertEquals('["INBOX","Archive"]', $res->output_response['mailboxes']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_list_block_sieve_output_defaults_to_empty_string() {
        $test = new Sieve_Output_Test('list_block_sieve_output', 'sievefilters');

        $res = $test->run();

        $this->assertEquals('', $res->output_response['ajax_list_block_sieve']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sievefilters_settings_link_is_hidden_when_sieve_disabled() {
        $test = new Sieve_Output_Test('sievefilters_settings_link', 'sievefilters');
        $test->handler_response = array('sieve_filters_enabled' => false);

        $res = $test->run();

        $this->assertArrayNotHasKey(0, $res->output_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_sievefilters_settings_link_renders_both_menu_entries() {
        $test = new Sieve_Output_Test('sievefilters_settings_link', 'sievefilters');
        $test->handler_response = array('sieve_filters_enabled' => true);

        $res = $test->run();
        $html = $res->output_response[0];

        $this->assertStringContainsString('menu_sieve_filters', $html);
        $this->assertStringContainsString('page=sieve_filters', $html);
        $this->assertStringContainsString('menu_block_list', $html);
        $this->assertStringContainsString('page=block_list', $html);
    }

    /* ------------------------------------------------------------------ *
     * the settings row that gates every sieve feature
     * ------------------------------------------------------------------ */

    /**
     * Asserted against the constant rather than a literal so the test does not
     * break if the shipped default flips.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_enable_sieve_filter_setting_offers_a_reset_when_changed_from_default() {
        $test = new Sieve_Output_Test('enable_sieve_filter_setting', 'sievefilters');
        $test->handler_response = array(
            'user_settings' => array('enable_sieve_filter' => !DEFAULT_ENABLE_SIEVE_FILTER),
        );

        $res = $test->run();
        $html = $res->output_response[0];

        $this->assertStringContainsString('id="enable_sieve_filter"', $html);
        $this->assertStringContainsString('name="enable_sieve_filter"', $html);
        $this->assertStringContainsString('reset_default_value_checkbox', $html);
        $this->assertStringContainsString(
            'data-default-value="'.(DEFAULT_ENABLE_SIEVE_FILTER ? 'true' : 'false').'"',
            $html
        );
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_enable_sieve_filter_setting_hides_reset_at_default() {
        $test = new Sieve_Output_Test('enable_sieve_filter_setting', 'sievefilters');
        $test->handler_response = array(
            'user_settings' => array('enable_sieve_filter' => DEFAULT_ENABLE_SIEVE_FILTER),
        );

        $res = $test->run();
        $html = $res->output_response[0];

        $this->assertStringNotContainsString('reset_default_value_checkbox', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_enable_sieve_filter_setting_checks_the_box_when_enabled() {
        $test = new Sieve_Output_Test('enable_sieve_filter_setting', 'sievefilters');
        $test->handler_response = array('user_settings' => array('enable_sieve_filter' => true));

        $res = $test->run();

        $this->assertStringContainsString('checked="checked"', $res->output_response[0]);
    }
}
