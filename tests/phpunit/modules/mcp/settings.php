<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for the "API and MCP" settings page
 */
class Hm_Test_MCP_Settings extends TestCase {

    private $db_config;

    private $accounts = [
        'srv_a' => ['id' => 'srv_a', 'name' => 'Work', 'user' => 'work@example.com', 'server' => 'imap.example.com', 'type' => 'imap'],
        'srv_b' => ['id' => 'srv_b', 'name' => 'Home', 'user' => 'home@example.com', 'server' => 'imap.example.com', 'type' => 'imap', 'hide' => true],
    ];

    public function setUp(): void {
        require __DIR__.'/../../helpers.php';
        require_once APP_PATH.'modules/mcp/modules.php';
        $this->db_config = new Hm_Mock_Config();
        setup_db($this->db_config);
        $store = new Hm_MCP_Store($this->db_config);
        if (!$store->available()) {
            $this->markTestSkipped('MCP tables are not available in the test database');
        }
        $dbh = Hm_DB::connect($this->db_config);
        foreach (['hm_mcp_settings', 'hm_mcp_connections', 'hm_mcp_tokens', 'hm_mcp_rate_limits', 'hm_mcp_activity'] as $table) {
            $dbh->exec('delete from '.$table);
        }
    }

    private function store() {
        return new Hm_MCP_Store($this->db_config);
    }

    private function handler($post = [], $auth = true, $get = []) {
        $test = new Handler_Test('mcp_settings_page', 'mcp');
        $test->config = array_merge($this->db_config->dump(), ['mcp_public_url' => 'https://mail.example.com']);
        $test->user_config = ['imap_servers' => $this->accounts];
        $test->session = ['username' => 'alice'];
        $test->post = $post;
        $test->get = $get;
        $test->prep();
        $test->ses_obj->auth_state = $auth;
        return $test->run_only()->handler_response;
    }

    private function messages() {
        return array_column(Hm_Msgs::getRaw(), 'text');
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_defaults() {
        $res = $this->handler();
        $this->assertTrue($res['mcp_available']);
        $this->assertTrue($res['mcp_configured']);
        $this->assertSame('https://mail.example.com', $res['mcp_public_url']);
        $this->assertFalse($res['mcp_settings']['enabled']);
        $this->assertTrue($res['mcp_settings']['permissions']['read']);
        $this->assertFalse($res['mcp_settings']['permissions']['delete_permanent']);
        $this->assertSame([], $res['mcp_connections']);
        $this->assertSame(['srv_a', 'srv_b'], array_column($res['mcp_accounts'], 'id'));
        $this->assertTrue($res['mcp_accounts'][1]['hidden']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_settings() {
        $this->handler(['mcp_action' => 'save_settings', 'mcp_enabled' => true,
            'mcp_permissions' => ['read', 'send', 'bogus'], 'mcp_account_mode' => 'selected',
            'mcp_account_ids' => ['srv_b', 'unknown']]);
        $this->assertContains('API and MCP settings saved', $this->messages());
        $settings = $this->store()->settings('alice');
        $this->assertTrue($settings['enabled']);
        $this->assertTrue($settings['permissions']['read']);
        $this->assertTrue($settings['permissions']['send']);
        $this->assertFalse($settings['permissions']['organize']);
        $this->assertSame(['mode' => 'selected', 'ids' => ['srv_b']], $settings['accounts']);
        $this->assertNotSame('', $settings['profile_id']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_save_settings_requires_an_account_when_selecting() {
        $this->handler(['mcp_action' => 'save_settings', 'mcp_enabled' => true,
            'mcp_permissions' => ['read'], 'mcp_account_mode' => 'selected', 'mcp_account_ids' => ['unknown']]);
        $this->assertContains('Select at least one account', $this->messages());
        $this->assertFalse($this->store()->settings('alice')['enabled']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_wrong_password_does_not_create_a_token() {
        $res = $this->handler(['mcp_action' => 'create_token', 'mcp_name' => 'Script', 'mcp_password' => 'wrong'], false);
        $this->assertContains('Incorrect password', $this->messages());
        $this->assertArrayNotHasKey('mcp_new_token', $res);
        $this->assertSame([], $res['mcp_connections']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_password_is_required() {
        $res = $this->handler(['mcp_action' => 'create_token', 'mcp_name' => 'Script', 'mcp_password' => '']);
        $this->assertContains('Your password is required', $this->messages());
        $this->assertSame([], $res['mcp_connections']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_create_token_with_custom_limits_capped_by_global() {
        $res = $this->handler(['mcp_action' => 'create_token', 'mcp_name' => 'Claude Code', 'mcp_password' => 'secret',
            'mcp_perm_mode' => 'custom', 'mcp_permissions' => ['read', 'delete_permanent'],
            'mcp_account_mode' => 'selected', 'mcp_account_ids' => ['srv_a'], 'mcp_expires' => 30]);
        $this->assertContains('Token created', $this->messages());
        $this->assertTrue($res['no_redirect']);
        $token = $res['mcp_new_token']['token'];
        $this->assertStringStartsWith('cyp_pat_', $token);

        $this->assertCount(1, $res['mcp_connections']);
        $connection = $res['mcp_connections'][0];
        $this->assertArrayNotHasKey('sealed_password', $connection);
        $this->assertSame('Claude Code', $connection['name']);
        $this->assertSame('personal', $connection['kind']);
        /* delete_permanent is disabled globally, so the token cannot get it */
        $this->assertTrue($connection['permissions']['read']);
        $this->assertFalse($connection['permissions']['delete_permanent']);
        $this->assertSame(['srv_a'], $connection['accounts']);
        $this->assertGreaterThan(time() + 29 * 86400, $connection['expires_at']);

        $found = $this->store()->find_token($token, ['personal']);
        $this->assertSame($connection['id'], $found['connection_id']);
        $stored = $this->store()->connection($connection['id']);
        $this->assertSame('secret', $this->store()->open_password($stored, $found['key']));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_create_token_inherits_by_default() {
        $res = $this->handler(['mcp_action' => 'create_token', 'mcp_name' => '', 'mcp_password' => 'secret', 'mcp_expires' => 0]);
        $connection = $res['mcp_connections'][0];
        $this->assertSame('Personal access token', $connection['name']);
        $this->assertNull($connection['permissions']);
        $this->assertNull($connection['accounts']);
        $this->assertSame(0, $connection['expires_at']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_update_and_revoke_connection() {
        list($id) = $this->store()->create_connection('alice', 'personal', 'Old', 'pw', ['permissions' => ['read' => true]]);
        list($other) = $this->store()->create_connection('bob', 'personal', 'Bob', 'pw');

        $this->handler(['mcp_action' => 'update_connection', 'mcp_connection_id' => $id, 'mcp_name' => 'New name',
            'mcp_perm_mode' => 'inherit', 'mcp_account_mode' => 'inherit']);
        $connection = $this->store()->connection($id);
        $this->assertSame('New name', $connection['name']);
        $this->assertNull($connection['permissions']);

        $this->handler(['mcp_action' => 'update_connection', 'mcp_connection_id' => $other, 'mcp_name' => 'Hijack']);
        $this->assertSame('Bob', $this->store()->connection($other)['name']);
        $this->assertContains('Connection not found', $this->messages());

        $this->handler(['mcp_action' => 'revoke_connection', 'mcp_connection_id' => $other]);
        $this->assertNotFalse($this->store()->connection($other));
        $res = $this->handler(['mcp_action' => 'revoke_connection', 'mcp_connection_id' => $id]);
        $this->assertFalse($this->store()->connection($id));
        $this->assertSame([], $res['mcp_connections']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_output_escapes_values_and_shows_the_new_token_once() {
        $test = new Output_Test('mcp_settings_content', 'mcp');
        $test->handler_response = [
            'mcp_available' => true,
            'mcp_configured' => true,
            'mcp_public_url' => 'https://mail.example.com',
            'mcp_accounts' => Hm_MCP_Permissions::account_list($this->accounts),
            'mcp_settings' => Hm_MCP_Store::default_settings(),
            'mcp_connections' => [[
                'id' => 'con_1', 'username' => 'alice', 'kind' => 'personal', 'name' => '<script>x</script>',
                'client_id' => null, 'permissions' => null, 'accounts' => ['srv_a'], 'status' => 'reauth',
                'created_at' => 1, 'last_used_at' => 0, 'expires_at' => 0,
            ]],
            'mcp_new_token' => ['name' => 'Script', 'token' => 'cyp_pat_example'],
        ];
        $html = implode('', $test->run()->output_response);
        $this->assertStringNotContainsString('<script>x</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $html);
        $this->assertSame(1, substr_count($html, 'cyp_pat_example'));
        $this->assertStringContainsString('https://mail.example.com/mcp', $html);
        $this->assertStringContainsString('name="hm_page_key"', $html);
        $this->assertStringContainsString('value="revoke_connection"', $html);
        $this->assertStringContainsString('Needs to reconnect', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_output_when_unavailable() {
        $test = new Output_Test('mcp_settings_content', 'mcp');
        $test->handler_response = ['mcp_available' => false];
        $html = implode('', $test->run()->output_response);
        $this->assertStringContainsString('database tables are missing', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_settings_changes_are_logged_and_activity_is_paged() {
        $res = $this->handler(['mcp_action' => 'create_token', 'mcp_name' => 'Script', 'mcp_password' => 'secret', 'mcp_expires' => 7]);
        $id = $res['mcp_connections'][0]['id'];
        $this->handler(['mcp_action' => 'save_settings', 'mcp_enabled' => true, 'mcp_permissions' => ['read'], 'mcp_account_mode' => 'all']);
        $res = $this->handler(['mcp_action' => 'revoke_connection', 'mcp_connection_id' => $id]);
        $ops = array_column($res['mcp_activity'], 'operation');
        $this->assertSame(['revoke_connection', 'save_settings', 'create_token'], $ops);
        $this->assertSame('settings', $res['mcp_activity'][0]['channel']);
        $this->assertSame('Script', $res['mcp_activity'][0]['connection_name']);
        $this->assertSame(['expires_days' => 7, 'permissions' => 'global', 'accounts' => 'global'], $res['mcp_activity'][2]['summary']);
        $this->assertStringNotContainsString('cyp_pat_', json_encode($res['mcp_activity']));
        $this->assertStringNotContainsString('secret', json_encode($res['mcp_activity']));
        $this->assertSame([$id => 'Script'], $res['mcp_activity_connections']);

        $store = $this->store();
        for ($i = 0; $i < 60; $i++) {
            $store->log_activity(['username' => 'alice', 'connection_id' => 'con_other', 'connection_name' => 'Other',
                'channel' => 'mcp', 'operation' => 'list_messages', 'outcome' => 'ok']);
        }
        $page1 = $this->handler();
        $this->assertSame(63, $page1['mcp_activity_total']);
        $this->assertSame(2, $page1['mcp_activity_pages']);
        $this->assertCount(50, $page1['mcp_activity']);
        $page2 = $this->handler([], true, ['mcp_activity_page' => 2]);
        $this->assertCount(13, $page2['mcp_activity']);
        $filtered = $this->handler([], true, ['mcp_activity' => $id]);
        $this->assertSame($id, $filtered['mcp_activity_filter']);
        $this->assertSame(2, $filtered['mcp_activity_total']);
        $unknown = $this->handler([], true, ['mcp_activity' => 'con_not_mine']);
        $this->assertNull($unknown['mcp_activity_filter']);

        $cleared = $this->handler(['mcp_action' => 'clear_activity']);
        $this->assertContains('Activity log cleared', $this->messages());
        $this->assertSame(0, $cleared['mcp_activity_total']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_activity_output() {
        $test = new Output_Test('mcp_settings_content', 'mcp');
        $test->handler_response = [
            'mcp_available' => true,
            'mcp_configured' => true,
            'mcp_public_url' => 'https://mail.example.com',
            'mcp_accounts' => Hm_MCP_Permissions::account_list($this->accounts),
            'mcp_settings' => Hm_MCP_Store::default_settings(),
            'mcp_connections' => [],
            'mcp_activity' => [
                ['id' => 2, 'connection_id' => 'con_1', 'connection_name' => '<b>Bot</b>', 'channel' => 'mcp', 'operation' => 'search_messages',
                    'permission' => 'read', 'outcome' => 'denied', 'summary' => ['error' => 'permission_denied'], 'created_at' => 1800000000],
                ['id' => 1, 'connection_id' => 'con_1', 'connection_name' => '<b>Bot</b>', 'channel' => 'link', 'operation' => 'download_attachment',
                    'permission' => 'read', 'outcome' => 'ok', 'summary' => ['account_id' => 'srv_a', 'readable' => true], 'created_at' => 1799999000],
            ],
            'mcp_activity_total' => 120,
            'mcp_activity_page' => 2,
            'mcp_activity_pages' => 3,
            'mcp_activity_filter' => 'con_1',
            'mcp_activity_connections' => ['con_1' => '<b>Bot</b>'],
            'page_param_name' => 'page',
        ];
        $html = implode('', $test->run()->output_response);
        $this->assertStringNotContainsString('<b>Bot</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;Bot&lt;/b&gt;', $html);
        $this->assertStringContainsString('Search messages', $html);
        $this->assertStringContainsString('Downloaded an attachment', $html);
        $this->assertStringContainsString('text-bg-warning', $html);
        $this->assertStringContainsString('work@example.com', $html);
        $this->assertStringContainsString('value="clear_activity"', $html);
        $this->assertStringContainsString('mcp_activity_page=1', $html);
        $this->assertStringContainsString('mcp_activity_page=3', $html);
        $this->assertStringContainsString('<option value="con_1" selected>', $html);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_module_strings_are_in_the_language_files() {
        $en = require APP_PATH.'language/en.php';
        $es = require APP_PATH.'language/es.php';
        $strings = [];
        $source = file_get_contents(APP_PATH.'modules/mcp/modules.php');
        preg_match_all("/(?:trans|Hm_Msgs::add|section)\\('((?:[^'\\\\]|\\\\.)*)'/", $source, $matches);
        $strings = array_merge($strings, $matches[1]);
        preg_match_all("/hm_trans\\('((?:[^'\\\\]|\\\\.)*)'/", file_get_contents(APP_PATH.'modules/mcp/site.js'), $matches);
        $strings = array_merge($strings, $matches[1]);
        preg_match_all("/(?:trans|text)\\('((?:[^'\\\\]|\\\\.)*)'/", file_get_contents(APP_PATH.'modules/mcp/hm-mcp-oauth.php'), $matches);
        $strings = array_merge($strings, $matches[1], array_values(Hm_MCP_OAuth::MESSAGES),
            array_values(Hm_Output_mcp_settings_content::REASONS), array_values(Hm_Output_mcp_settings_content::MODES));
        foreach (Hm_MCP_Permissions::keys() as $key) {
            $strings[] = Hm_MCP_Permissions::label($key);
            $strings[] = Hm_MCP_Permissions::description($key);
        }
        foreach ((new Hm_MCP_Catalog())->all() as $op) {
            $strings[] = $op['title'];
        }
        $strings = array_merge($strings, array_values(Hm_Output_mcp_settings_content::OTHER_OPERATIONS),
            array_values(Hm_Output_mcp_settings_content::CHANNELS), array_values(Hm_Output_mcp_settings_content::DETAIL_LABELS),
            array_column(Hm_Output_mcp_settings_content::OUTCOMES, 0));
        foreach (array_unique($strings) as $string) {
            $string = str_replace("\\'", "'", $string);
            $this->assertArrayHasKey($string, $en, 'missing in en.php: '.$string);
            $this->assertArrayHasKey($string, $es, 'missing in es.php: '.$string);
            $this->assertIsString($es[$string], 'not translated in es.php: '.$string);
        }
    }
}
