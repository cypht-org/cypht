<?php

use PHPUnit\Framework\TestCase;

require_once APP_PATH.'modules/mcp/hm-mcp.php';

/**
 * tests for Hm_MCP_Store, run against the test database of the current engine
 */
class Hm_Test_MCP_Store extends TestCase {

    private $store;
    private $now;

    public function setUp(): void {
        $config = new Hm_Mock_Config();
        setup_db($config);
        $this->store = new Hm_MCP_Store($config);
        if (!$this->store->available()) {
            $this->markTestSkipped('MCP tables are not available in the test database');
        }
        $this->now = 1800000000;
        $this->store->clock = function () { return $this->now; };
        $dbh = Hm_DB::connect($config);
        foreach (['hm_mcp_settings', 'hm_mcp_connections', 'hm_mcp_tokens', 'hm_mcp_oauth_clients', 'hm_mcp_sessions', 'hm_mcp_activity', 'hm_mcp_rate_limits'] as $table) {
            $dbh->exec('delete from '.$table);
        }
    }

    public function test_settings_defaults_and_save() {
        $settings = $this->store->settings('alice');
        $this->assertFalse($settings['enabled']);
        $this->assertSame('', $settings['profile_id']);
        $this->assertTrue($settings['permissions']['read']);
        $this->assertFalse($settings['permissions']['send']);

        $created = $this->store->settings('alice', true);
        $this->assertMatchesRegularExpression('/^prf_[0-9a-f]{24}$/', $created['profile_id']);
        $this->assertSame($created['profile_id'], $this->store->settings('alice', true)['profile_id']);

        $permissions = $created['permissions'];
        $permissions['send'] = true;
        $permissions['read'] = false;
        $this->assertTrue($this->store->save_settings('alice', [
            'enabled' => true,
            'permissions' => $permissions,
            'accounts' => ['mode' => 'selected', 'ids' => ['a1', 'a1', 'b2']],
        ]));
        $saved = $this->store->settings('alice');
        $this->assertTrue($saved['enabled']);
        $this->assertTrue($saved['permissions']['send']);
        $this->assertFalse($saved['permissions']['read']);
        $this->assertSame(['mode' => 'selected', 'ids' => ['a1', 'b2']], $saved['accounts']);
        $this->assertSame($created['profile_id'], $saved['profile_id']);
    }

    public function test_connection_password_is_sealed_with_the_connection_key() {
        list($id, $key) = $this->store->create_connection('alice', 'personal', 'Script', 'p4ss w0rd', [
            'permissions' => ['read' => true],
            'accounts' => ['x', 'y'],
        ]);
        $connection = $this->store->connection($id);
        $this->assertSame('alice', $connection['username']);
        $this->assertSame('active', $connection['status']);
        $this->assertSame(['x', 'y'], $connection['accounts']);
        $this->assertTrue($connection['permissions']['read']);
        $this->assertFalse($connection['permissions']['send']);
        $this->assertStringNotContainsString('p4ss', $connection['sealed_password']);
        $this->assertSame('p4ss w0rd', $this->store->open_password($connection, $key));
        $this->assertFalse($this->store->open_password($connection, Hm_MCP_Crypto::random_key()));

        /* a sealed password cannot be moved to another connection */
        list($other_id, $other_key) = $this->store->create_connection('mallory', 'personal', 'X', 'other');
        $other = $this->store->connection($other_id);
        $other['sealed_password'] = $connection['sealed_password'];
        $this->assertFalse($this->store->open_password($other, $key));
    }

    public function test_connection_cache() {
        list($id, $key) = $this->store->create_connection('alice', 'oauth', 'ChatGPT', 'pw');
        $connection = $this->store->connection($id);
        $this->assertSame([], $this->store->open_cache($connection, $key));
        $this->assertTrue($this->store->save_cache($connection, $key, ['tokens' => ['s1' => ['pass' => 'at', 'expiration' => 5]]]));
        $connection = $this->store->connection($id);
        $this->assertSame(['tokens' => ['s1' => ['pass' => 'at', 'expiration' => 5]]], $this->store->open_cache($connection, $key));
        $this->assertSame([], $this->store->open_cache($connection, Hm_MCP_Crypto::random_key()));
    }

    public function test_tokens_unwrap_the_connection_key() {
        list($id, $key) = $this->store->create_connection('alice', 'oauth', 'ChatGPT', 'pw');
        $token = $this->store->issue_token($id, 'access', $key, 3600, ['resource' => 'https://mail.example.com/mcp']);
        $this->assertStringStartsWith('cyp_at_', $token);

        $found = $this->store->find_token($token, ['access']);
        $this->assertSame($key, $found['key']);
        $this->assertSame($id, $found['connection_id']);
        $this->assertSame('https://mail.example.com/mcp', $found['data']['resource']);
        $this->assertFalse($this->store->find_token($token, ['refresh']));
        $this->assertFalse($this->store->find_token($token.'x', ['access']));
        $this->assertFalse($this->store->find_token('short', ['access']));

        $this->now += 3601;
        $this->assertFalse($this->store->find_token($token, ['access']));
    }

    public function test_single_use_tokens() {
        list($id, $key) = $this->store->create_connection('alice', 'oauth', 'ChatGPT', 'pw');
        $code = $this->store->issue_token($id, 'code', $key, 300);
        $found = $this->store->find_token($code, ['code']);
        $this->assertTrue($this->store->mark_token_used($found['hash']));
        $this->assertFalse($this->store->mark_token_used($found['hash']));
        $this->assertFalse($this->store->find_token($code, ['code']));
        $this->assertSame($id, $this->store->find_token($code, ['code'], true)['connection_id']);
    }

    public function test_delete_connection_removes_tokens_and_checks_owner() {
        list($id, $key) = $this->store->create_connection('alice', 'personal', 'Script', 'pw');
        $token = $this->store->issue_token($id, 'personal', $key, 0);
        $this->assertSame(1, $this->store->count_tokens($id, 'personal'));
        $this->assertFalse($this->store->delete_connection($id, 'mallory'));
        $this->assertNotFalse($this->store->find_token($token, ['personal']));
        $this->assertTrue($this->store->delete_connection($id, 'alice'));
        $this->assertFalse($this->store->find_token($token, ['personal']));
        $this->assertFalse($this->store->connection($id));
    }

    public function test_pending_connections_are_hidden_and_purged() {
        list($pending) = $this->store->create_connection('alice', 'oauth', 'ChatGPT', 'pw', ['status' => 'pending']);
        list($active) = $this->store->create_connection('alice', 'personal', 'Script', 'pw');
        $ids = array_column($this->store->connections('alice'), 'id');
        $this->assertSame([$active], $ids);
        $this->assertSame([], $this->store->connections('alice', ['oauth']));
        $this->now += 3600;
        $this->store->purge(90, 86400);
        $this->assertFalse($this->store->connection($pending));
        $this->assertNotFalse($this->store->connection($active));
    }

    public function test_update_connection() {
        list($id) = $this->store->create_connection('alice', 'oauth', 'ChatGPT', 'pw');
        $this->assertTrue($this->store->update_connection($id, ['name' => 'Renamed', 'permissions' => ['send' => true],
            'accounts' => ['a'], 'status' => 'reauth', 'last_used_at' => 123]));
        $connection = $this->store->connection($id);
        $this->assertSame('Renamed', $connection['name']);
        $this->assertTrue($connection['permissions']['send']);
        $this->assertFalse($connection['permissions']['read']);
        $this->assertSame(['a'], $connection['accounts']);
        $this->assertSame('reauth', $connection['status']);
        $this->assertSame(123, $connection['last_used_at']);
        $this->assertTrue($this->store->update_connection($id, ['permissions' => null, 'accounts' => null]));
        $connection = $this->store->connection($id);
        $this->assertNull($connection['permissions']);
        $this->assertNull($connection['accounts']);
    }

    public function test_oauth_clients() {
        $client = ['client_id' => 'cl_1', 'kind' => 'dcr', 'client_name' => 'ChatGPT',
            'redirect_uris' => ['https://chatgpt.com/connector/oauth/abc'], 'metadata' => ['grant_types' => ['authorization_code']]];
        $this->assertTrue($this->store->save_client($client));
        $stored = $this->store->client('cl_1');
        $this->assertSame(['https://chatgpt.com/connector/oauth/abc'], $stored['redirect_uris']);
        $this->assertSame(0, $stored['last_used_at']);
        $this->assertFalse($this->store->client('missing'));

        $this->now += 86400 * 8;
        $this->store->save_client(['client_id' => 'cl_2'] + $client);
        $this->store->touch_client('cl_2');
        $this->store->purge(90, 86400);
        $this->assertFalse($this->store->client('cl_1'));
        $this->assertNotFalse($this->store->client('cl_2'));
    }

    public function test_sessions_are_bound_to_a_connection() {
        $this->assertTrue($this->store->session_write('s1', 'con_a', '{"x":1}'));
        $this->assertSame('{"x":1}', $this->store->session_read('s1', 'con_a', 3600));
        $this->assertFalse($this->store->session_read('s1', 'con_b', 3600));
        $this->assertFalse($this->store->session_write('s1', 'con_b', 'stolen'));
        $this->assertTrue($this->store->session_write('s1', 'con_a', '{"x":2}'));
        $this->assertSame('{"x":2}', $this->store->session_read('s1', 'con_a', 3600));
        $this->now += 7200;
        $this->assertFalse($this->store->session_read('s1', 'con_a', 3600));
    }

    public function test_activity_log() {
        foreach (['list_messages', 'get_message', 'send_draft'] as $i => $op) {
            $this->now += 10;
            $this->assertTrue($this->store->log_activity(['username' => 'alice', 'connection_id' => 'con_a', 'connection_name' => 'ChatGPT',
                'channel' => 'mcp', 'operation' => $op, 'permission' => 'read', 'outcome' => $i == 2 ? 'denied' : 'ok',
                'summary' => ['messages' => 3]]));
        }
        $this->store->log_activity(['username' => 'bob', 'channel' => 'rest', 'operation' => 'x', 'outcome' => 'ok']);
        $rows = $this->store->activity('alice');
        $this->assertCount(3, $rows);
        $this->assertSame('send_draft', $rows[0]['operation']);
        $this->assertSame(['messages' => 3], $rows[0]['summary']);
        $this->assertCount(1, $this->store->activity('alice', 1, 1));
        $this->assertCount(0, $this->store->activity('alice', 10, 0, 'con_b'));
        $this->assertTrue($this->store->clear_activity('alice'));
        $this->assertCount(0, $this->store->activity('alice'));
        $this->assertCount(1, $this->store->activity('bob'));
    }

    public function test_rate_limit() {
        $this->assertTrue($this->store->rate_limit('ip:1.2.3.4', 2, 60));
        $this->assertTrue($this->store->rate_limit('ip:1.2.3.4', 2, 60));
        $this->assertFalse($this->store->rate_limit('ip:1.2.3.4', 2, 60));
        $this->assertTrue($this->store->rate_limit('ip:5.6.7.8', 2, 60));
        $this->now += 61;
        $this->assertTrue($this->store->rate_limit('ip:1.2.3.4', 2, 60));
        $this->store->rate_reset('ip:1.2.3.4');
        $this->assertTrue($this->store->rate_limit('ip:1.2.3.4', 1, 60));
    }
}
