<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/sieve_test_helpers.php';

/**
 * Output modules for automatic actions: the toolbar dropdown listing the
 * cypht-managed sieve filter scripts that run server side on incoming mail.
 */
class Hm_Test_Sievefilters_Automatic_Actions_Outputs extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'modules/sievefilters/modules.php';
        Hm_Msgs::flush();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_automatic_actions_renders_filters() {
        $test = new Sieve_Output_Test('message_list_automatic_actions', 'sievefilters');
        $test->handler_response = array(
            'sieve_filters_enabled' => true,
            'mailbox_name' => 'Primary Account',
            'automatic_actions' => array(
                array('id' => 'from_list-10-cyphtfilter', 'name' => 'from list', 'source' => 'message_list'),
            ),
        );

        $res = $test->run();
        $html = $res->output_response['msg_controls_automatic_actions'];

        $this->assertStringContainsString('msg_filter_action', $html);
        $this->assertStringContainsString('data-filter-id="from_list-10-cyphtfilter"', $html);
        $this->assertStringContainsString('data-filter-name="from list"', $html);
        $this->assertStringContainsString('id="add_automatic_action_button"', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_message_list_automatic_actions_renders_nothing_when_sieve_disabled() {
        $test = new Sieve_Output_Test('message_list_automatic_actions', 'sievefilters');
        $test->handler_response = array('sieve_filters_enabled' => false);

        $res = $test->run();

        $this->assertArrayNotHasKey('msg_controls_automatic_actions', $res->output_response);
    }
}
