<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/sieve_test_helpers.php';

/**
 * Custom action buttons: user-defined, manually triggered actions saved per
 * IMAP account. Distinct from the automatic (sieve) actions below, which run
 * on the server against incoming mail without the user doing anything.
 */
class Hm_Test_Sievefilters_Custom_Actions_Handlers extends TestCase {

    public function setUp(): void {
        require_once APP_PATH.'modules/sievefilters/modules.php';
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

    /**
     * Custom actions are stored per account under a 'by_account' key, keyed by
     * the generated action id.
     */
    private function customActionsConfig($account = 'Primary Account') {
        return array(
            'by_account' => array(
                $account => array(
                    'ca_move' => array(
                        'id' => 'ca_move',
                        'name' => 'Move Important',
                        'actions' => array(
                            array('action' => 'move', 'value' => 'Important'),
                        ),
                    ),
                    'ca_flag' => array(
                        'id' => 'ca_flag',
                        'name' => 'Flag Message',
                        'actions' => array(
                            array('action' => 'flag', 'value' => 'flagged'),
                        ),
                    ),
                ),
            ),
        );
    }

    /* ------------------------------------------------------------------ *
     * load_custom_actions - resolving which account the buttons belong to
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_actions_returns_saved_custom_actions() {
        $test = new Sieve_Handler_Test('load_custom_actions', 'sievefilters');
        $test->get = array('list_path' => 'imap_serverA_INBOX');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();
        $actions = $res->handler_response['custom_actions'];

        $this->assertCount(2, $actions);
        $this->assertEquals('ca_move', $actions['ca_move']['id']);
        $this->assertEquals('Move Important', $actions['ca_move']['name']);
        $this->assertEquals('ca_flag', $actions['ca_flag']['id']);
        $this->assertEquals('Flag Message', $actions['ca_flag']['name']);
    }

    /**
     * Custom actions are namespaced per account, so an account with none of its
     * own must not inherit another account's buttons.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_actions_does_not_leak_actions_across_accounts() {
        $test = new Sieve_Handler_Test('load_custom_actions', 'sievefilters');
        $test->post = array('imap_account' => 'Secondary Account');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertSame(array(), $res->handler_response['custom_actions']);
        $this->assertArrayNotHasKey('custom_action_error', $res->handler_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_actions_prefers_posted_account_over_list_path() {
        $test = new Sieve_Handler_Test('load_custom_actions', 'sievefilters');
        $test->post = array('imap_account' => '  Primary Account  ');
        $test->get = array('list_path' => 'imap_serverB_INBOX');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertCount(2, $res->handler_response['custom_actions']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_actions_falls_back_to_mailbox_name_from_previous_handler() {
        $test = new Sieve_Handler_Test('load_custom_actions', 'sievefilters');
        $test->input = array('mailbox_name' => 'Primary Account');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertCount(2, $res->handler_response['custom_actions']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_actions_errors_when_account_cannot_be_resolved() {
        $test = new Sieve_Handler_Test('load_custom_actions', 'sievefilters');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals('Missing account', $res->handler_response['custom_action_error']);
        $this->assertArrayNotHasKey('custom_actions', $res->handler_response);
    }

    /**
     * An unknown server id in list_path must not resolve to an account.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_actions_errors_when_list_path_server_is_unknown() {
        $test = new Sieve_Handler_Test('load_custom_actions', 'sievefilters');
        $test->get = array('list_path' => 'imap_missingServer_INBOX');
        $test->user_config = array(
            'imap_servers' => $this->imapServersConfig(),
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals('Missing account', $res->handler_response['custom_action_error']);
    }

    /* ------------------------------------------------------------------ *
     * save_custom_action
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_creates_new_action_scoped_to_account() {
        $test = new Sieve_Handler_Test('save_custom_action', 'sievefilters');
        $test->post = array(
            'custom_action_name' => 'Archive and read',
            'actions_json' => json_encode(array(
                array('action' => 'move', 'value' => 'Archive'),
                array('action' => 'flag', 'value' => 'seen'),
            )),
            'imap_account' => 'Primary Account',
        );
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $this->assertTrue($res->handler_response['custom_action_saved']);
        $id = $res->handler_response['custom_action_id'];
        $this->assertStringStartsWith('ca_', $id);

        $saved = $res->user_config->get('custom_actions');
        $this->assertArrayHasKey('Primary Account', $saved['by_account']);
        $action = $saved['by_account']['Primary Account'][$id];
        $this->assertEquals($id, $action['id']);
        $this->assertEquals('Archive and read', $action['name']);
        $this->assertEquals(
            array(
                array('action' => 'move', 'value' => 'Archive'),
                array('action' => 'flag', 'value' => 'seen'),
            ),
            $action['actions']
        );
    }

    /**
     * Posting a known action_id updates in place rather than creating a duplicate.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_updates_existing_action_in_place() {
        $test = new Sieve_Handler_Test('save_custom_action', 'sievefilters');
        $test->post = array(
            'custom_action_name' => 'Move Somewhere Else',
            'actions_json' => json_encode(array(array('action' => 'move', 'value' => 'Later'))),
            'imap_account' => 'Primary Account',
            'action_id' => 'ca_move',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals('ca_move', $res->handler_response['custom_action_id']);
        $account_actions = $res->user_config->get('custom_actions')['by_account']['Primary Account'];
        $this->assertCount(2, $account_actions);
        $this->assertEquals('Move Somewhere Else', $account_actions['ca_move']['name']);
        $this->assertEquals(
            array(array('action' => 'move', 'value' => 'Later')),
            $account_actions['ca_move']['actions']
        );
        $this->assertEquals('Flag Message', $account_actions['ca_flag']['name']);
    }

    /**
     * An unknown action_id must be treated as a create, not an update.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_with_unknown_action_id_creates_new_action() {
        $test = new Sieve_Handler_Test('save_custom_action', 'sievefilters');
        $test->post = array(
            'custom_action_name' => 'Brand New',
            'actions_json' => json_encode(array(array('action' => 'keep', 'value' => ''))),
            'imap_account' => 'Primary Account',
            'action_id' => 'ca_does_not_exist',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $id = $res->handler_response['custom_action_id'];
        $this->assertNotEquals('ca_does_not_exist', $id);
        $this->assertCount(3, $res->user_config->get('custom_actions')['by_account']['Primary Account']);
    }

    /**
     * Actions arrive from JS as an object with numeric keys after drag/drop
     * reordering; they must be stored as a list so order is preserved.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_reindexes_sparse_action_keys() {
        $test = new Sieve_Handler_Test('save_custom_action', 'sievefilters');
        $test->post = array(
            'custom_action_name' => 'Reordered',
            'actions_json' => json_encode(array(
                '2' => array('action' => 'stop', 'value' => ''),
                '5' => array('action' => 'move', 'value' => 'Archive'),
            )),
            'imap_account' => 'Primary Account',
        );
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $id = $res->handler_response['custom_action_id'];
        $actions = $res->user_config->get('custom_actions')['by_account']['Primary Account'][$id]['actions'];
        $this->assertEquals(array(0, 1), array_keys($actions));
        $this->assertEquals('stop', $actions[0]['action']);
        $this->assertEquals('move', $actions[1]['action']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_requires_name_and_actions() {
        $test = new Sieve_Handler_Test('save_custom_action', 'sievefilters');
        $test->post = array('imap_account' => 'Primary Account');
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $this->assertEquals('Missing required fields', $res->handler_response['custom_action_error']);
        $this->assertArrayNotHasKey('custom_action_saved', $res->handler_response);
        $this->assertFalse($res->user_config->get('custom_actions'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_rejects_empty_action_list() {
        $test = new Sieve_Handler_Test('save_custom_action', 'sievefilters');
        $test->post = array(
            'custom_action_name' => 'Does nothing',
            'actions_json' => '[]',
            'imap_account' => 'Primary Account',
        );
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $this->assertEquals('At least one action is required', $res->handler_response['custom_action_error']);
        $this->assertFalse($res->user_config->get('custom_actions'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_rejects_malformed_actions_json() {
        $test = new Sieve_Handler_Test('save_custom_action', 'sievefilters');
        $test->post = array(
            'custom_action_name' => 'Broken',
            'actions_json' => 'not json at all',
            'imap_account' => 'Primary Account',
        );
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $this->assertEquals('At least one action is required', $res->handler_response['custom_action_error']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_requires_an_account() {
        $test = new Sieve_Handler_Test('save_custom_action', 'sievefilters');
        $test->post = array(
            'custom_action_name' => 'Orphan',
            'actions_json' => json_encode(array(array('action' => 'keep', 'value' => ''))),
            'imap_account' => '   ',
        );
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $this->assertEquals('Missing account', $res->handler_response['custom_action_error']);
        $this->assertFalse($res->user_config->get('custom_actions'));
    }

    /**
     * Saving for a second account must not clobber the first account's actions.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_custom_action_keeps_other_accounts_intact() {
        $test = new Sieve_Handler_Test('save_custom_action', 'sievefilters');
        $test->post = array(
            'custom_action_name' => 'Secondary only',
            'actions_json' => json_encode(array(array('action' => 'keep', 'value' => ''))),
            'imap_account' => 'Secondary Account',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $by_account = $res->user_config->get('custom_actions')['by_account'];
        $this->assertCount(2, $by_account['Primary Account']);
        $this->assertCount(1, $by_account['Secondary Account']);
    }

    /* ------------------------------------------------------------------ *
     * load_custom_action_by_id
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_action_by_id_returns_the_action() {
        $test = new Sieve_Handler_Test('load_custom_action_by_id', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'custom_action_id' => 'ca_flag',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals('ca_flag', $res->handler_response['custom_action']['id']);
        $this->assertEquals('Flag Message', $res->handler_response['custom_action']['name']);
        $this->assertArrayNotHasKey('custom_action_error', $res->handler_response);
    }

    /**
     * The edit modal rebuilds action rows by index, so a stored action map with
     * non-sequential keys has to come back as a list.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_action_by_id_reindexes_actions_as_a_list() {
        $test = new Sieve_Handler_Test('load_custom_action_by_id', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'custom_action_id' => 'ca_multi',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => array(
                'by_account' => array(
                    'Primary Account' => array(
                        'ca_multi' => array(
                            'id' => 'ca_multi',
                            'name' => 'Multi step',
                            'actions' => array(
                                '3' => array('action' => 'move', 'value' => 'Archive'),
                                '7' => array('action' => 'stop', 'value' => ''),
                            ),
                        ),
                    ),
                ),
            ),
        );

        $res = $test->run();
        $actions = $res->handler_response['custom_action']['actions'];

        $this->assertEquals(array(0, 1), array_keys($actions));
        $this->assertEquals('move', $actions[0]['action']);
        $this->assertEquals('stop', $actions[1]['action']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_action_by_id_errors_for_unknown_id() {
        $test = new Sieve_Handler_Test('load_custom_action_by_id', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'custom_action_id' => 'ca_nope',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals('Custom Action not found', $res->handler_response['custom_action_error']);
        $this->assertArrayNotHasKey('custom_action', $res->handler_response);
    }

    /**
     * An id that exists on another account must not be readable from this one.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_action_by_id_errors_for_other_accounts_id() {
        $test = new Sieve_Handler_Test('load_custom_action_by_id', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Secondary Account',
            'custom_action_id' => 'ca_move',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals('Custom Action not found', $res->handler_response['custom_action_error']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_action_by_id_requires_both_fields() {
        $test = new Sieve_Handler_Test('load_custom_action_by_id', 'sievefilters');
        $test->post = array('imap_account' => 'Primary Account');
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals('Missing required fields', $res->handler_response['custom_action_error']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_custom_action_by_id_skips_when_sieve_disabled() {
        $test = new Sieve_Handler_Test('load_custom_action_by_id', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'custom_action_id' => 'ca_flag',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => false,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertArrayNotHasKey('custom_action', $res->handler_response);
        $this->assertArrayNotHasKey('custom_action_error', $res->handler_response);
    }

    /* ------------------------------------------------------------------ *
     * load_account_custom_actions (settings page)
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_account_custom_actions_outputs_actions_for_the_mailbox() {
        $test = new Sieve_Handler_Test('load_account_custom_actions', 'sievefilters');
        $test->input = array('mailbox' => array('name' => 'Primary Account'));
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertCount(2, $res->handler_response['account_custom_actions']);
        $this->assertEquals('Move Important', $res->handler_response['account_custom_actions']['ca_move']['name']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_account_custom_actions_outputs_empty_list_for_account_with_none() {
        $test = new Sieve_Handler_Test('load_account_custom_actions', 'sievefilters');
        $test->input = array('mailbox' => array('name' => 'Secondary Account'));
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertSame(array(), $res->handler_response['account_custom_actions']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_account_custom_actions_does_nothing_without_a_mailbox() {
        $test = new Sieve_Handler_Test('load_account_custom_actions', 'sievefilters');
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertArrayNotHasKey('account_custom_actions', $res->handler_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_account_custom_actions_skips_when_sieve_disabled() {
        $test = new Sieve_Handler_Test('load_account_custom_actions', 'sievefilters');
        $test->input = array('mailbox' => array('name' => 'Primary Account'));
        $test->user_config = array(
            'enable_sieve_filter_setting' => false,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertArrayNotHasKey('account_custom_actions', $res->handler_response);
    }

    /* ------------------------------------------------------------------ *
     * delete_custom_action
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_custom_action_removes_and_persists() {
        $test = new Sieve_Handler_Test('delete_custom_action', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'custom_action_id' => 'ca_move',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals(1, $res->handler_response['custom_action_deleted']);
        $remaining = $res->user_config->get('custom_actions')['by_account']['Primary Account'];
        $this->assertArrayNotHasKey('ca_move', $remaining);
        $this->assertArrayHasKey('ca_flag', $remaining);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_custom_action_reports_zero_for_unknown_id() {
        $test = new Sieve_Handler_Test('delete_custom_action', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'custom_action_id' => 'ca_nope',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals(0, $res->handler_response['custom_action_deleted']);
        $this->assertCount(2, $res->user_config->get('custom_actions')['by_account']['Primary Account']);
    }

    /**
     * Deleting by an id owned by a different account must be a no-op.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_custom_action_reports_zero_for_unknown_account() {
        $test = new Sieve_Handler_Test('delete_custom_action', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Secondary Account',
            'custom_action_id' => 'ca_move',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals(0, $res->handler_response['custom_action_deleted']);
        $this->assertCount(2, $res->user_config->get('custom_actions')['by_account']['Primary Account']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_custom_action_reports_zero_when_fields_are_missing() {
        $test = new Sieve_Handler_Test('delete_custom_action', 'sievefilters');
        $test->post = array('imap_account' => 'Primary Account');
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals(0, $res->handler_response['custom_action_deleted']);
        $this->assertCount(2, $res->user_config->get('custom_actions')['by_account']['Primary Account']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_delete_custom_action_reports_zero_for_blank_values() {
        $test = new Sieve_Handler_Test('delete_custom_action', 'sievefilters');
        $test->post = array(
            'imap_account' => '  ',
            'custom_action_id' => '  ',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'custom_actions' => $this->customActionsConfig(),
        );

        $res = $test->run();

        $this->assertEquals(0, $res->handler_response['custom_action_deleted']);
    }

    /* ------------------------------------------------------------------ *
     * load_message_custom_actions_context (message page endpoint)
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_message_custom_actions_context_outputs_message_target() {
        $test = new Sieve_Handler_Test('load_message_custom_actions_context', 'sievefilters');
        $test->post = array(
            'imap_server_id' => 'serverA',
            'imap_msg_uid' => ' 42 ',
            'folder' => 'SU5CT1g=',
        );
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'imap_servers' => $this->imapServersConfig(),
        );

        $res = $test->run();

        $this->assertEquals('serverA', $res->handler_response['msg_server_id']);
        $this->assertEquals('42', $res->handler_response['msg_text_uid']);
        $this->assertEquals('SU5CT1g=', $res->handler_response['msg_folder']);
        $this->assertEquals('Primary Account', $res->handler_response['mailbox_name']);
        $this->assertTrue($res->handler_response['sieve_filters_enabled']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_message_custom_actions_context_nulls_missing_target_fields() {
        $test = new Sieve_Handler_Test('load_message_custom_actions_context', 'sievefilters');
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'imap_servers' => $this->imapServersConfig(),
        );

        $res = $test->run();

        $this->assertNull($res->handler_response['msg_server_id']);
        $this->assertNull($res->handler_response['msg_text_uid']);
        $this->assertNull($res->handler_response['msg_folder']);
        $this->assertArrayNotHasKey('mailbox_name', $res->handler_response);
    }

    /**
     * The dropdown is hidden when sieve is off, so the flag has to survive the
     * standalone endpoint too.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_message_custom_actions_context_reports_sieve_disabled() {
        $test = new Sieve_Handler_Test('load_message_custom_actions_context', 'sievefilters');
        $test->post = array('imap_server_id' => 'serverA');
        $test->user_config = array(
            'enable_sieve_filter_setting' => false,
            'imap_servers' => $this->imapServersConfig(),
        );

        $res = $test->run();

        $this->assertFalse($res->handler_response['sieve_filters_enabled']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_load_message_custom_actions_context_omits_name_for_unknown_server() {
        $test = new Sieve_Handler_Test('load_message_custom_actions_context', 'sievefilters');
        $test->post = array('imap_server_id' => 'serverZ');
        $test->user_config = array(
            'enable_sieve_filter_setting' => true,
            'imap_servers' => $this->imapServersConfig(),
        );

        $res = $test->run();

        $this->assertEquals('serverZ', $res->handler_response['msg_server_id']);
        $this->assertArrayNotHasKey('mailbox_name', $res->handler_response);
    }

    /* ------------------------------------------------------------------ *
     * apply_custom_action - validation and uid parsing only; everything past
     * that point needs live IMAP/SMTP and is covered by selenium
     * ------------------------------------------------------------------ */

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_apply_custom_action_requires_account_uids_and_actions() {
        $test = new Sieve_Handler_Test('apply_custom_action', 'sievefilters');
        $test->post = array('imap_account' => 'Primary Account');
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $this->assertEquals('Missing required fields', $res->handler_response['custom_action_error']);
        $this->assertArrayNotHasKey('apply_success', $res->handler_response);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_apply_custom_action_rejects_empty_uid_list() {
        $test = new Sieve_Handler_Test('apply_custom_action', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'uids' => '[]',
            'actions_json' => json_encode(array(array('action' => 'keep', 'value' => ''))),
        );
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $this->assertEquals('No messages selected', $res->handler_response['custom_action_error']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_apply_custom_action_rejects_empty_action_list() {
        $test = new Sieve_Handler_Test('apply_custom_action', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'uids' => json_encode(array('imap_serverA_42_494e424f58')),
            'actions_json' => '[]',
        );
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $this->assertEquals('No actions defined', $res->handler_response['custom_action_error']);
    }

    /**
     * UIDs must look like imap_{server}_{uid}_{hex folder}; anything else is
     * dropped, and if nothing survives the request is refused rather than
     * silently succeeding against zero messages.
     *
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_apply_custom_action_rejects_unparseable_uids() {
        $test = new Sieve_Handler_Test('apply_custom_action', 'sievefilters');
        $test->post = array(
            'imap_account' => 'Primary Account',
            'uids' => json_encode(array(
                'garbage',
                'imap_serverA_42',
                'feed_serverA_42_494e424f58',
                'imap_serverA_42_494e424f58_extra',
            )),
            'actions_json' => json_encode(array(array('action' => 'keep', 'value' => ''))),
        );
        $test->user_config = array('enable_sieve_filter_setting' => true);

        $res = $test->run();

        $this->assertEquals('Could not parse message IDs', $res->handler_response['custom_action_error']);
        $this->assertArrayNotHasKey('apply_success', $res->handler_response);
    }
}
