<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for /mcp and /api/v1 through the router, with the real store and SDK
 * and in memory mailboxes
 */
class Hm_Test_MCP_Endpoint extends TestCase {

    private $site;
    private $services;
    private $store;
    private $router;
    private $personal;
    private $personal_id;

    private $servers = [
        'acc1' => ['id' => 'acc1', 'name' => 'Work', 'user' => 'work@example.com', 'server' => 'imap.example.com', 'type' => 'imap'],
    ];

    public function setUp(): void {
        require_once __DIR__.'/fakes.php';
        $this->site = new Hm_Mock_Config();
        setup_db($this->site);
        $this->site->set('mcp_public_url', 'https://mail.example.com');
        $this->site->set('app_name', 'Test Mail');
        $this->site->mods = ['core', 'imap', 'mcp'];
        $this->services = new Hm_MCP_Services($this->site);
        $this->store = $this->services->store();
        if (!$this->store->available()) {
            $this->markTestSkipped('MCP tables are not available in the test database');
        }
        $dbh = Hm_DB::connect($this->site);
        foreach (['hm_mcp_settings', 'hm_mcp_connections', 'hm_mcp_tokens', 'hm_mcp_sessions', 'hm_mcp_activity', 'hm_mcp_rate_limits'] as $table) {
            $dbh->exec('delete from '.$table);
        }
        $this->store->settings('alice', true);
        $this->store->save_settings('alice', ['enabled' => true]);
        list($this->personal_id, $key) = $this->store->create_connection('alice', 'personal', 'Script', 'pw');
        $this->personal = $this->store->issue_token($this->personal_id, 'personal', $key, 3600);
        $services = $this->services;
        $servers = $this->servers;
        $this->services->context_factory = function ($principal) use ($services, $servers) {
            $context = new Hm_MCP_Fake_Context($services->site_config, $services->config, $services->store(), $principal,
                new Hm_Mock_Config(), new Hm_MCP_Session(), $servers);
            $context->mailbox_objects = ['acc1' => new Hm_MCP_Fake_Mailbox(['INBOX' => [
                '1' => hm_mcp_fake_message('Hello', 'Bob <bob@example.net>', 'Mon, 14 Sep 2026 10:00:00 +0000'),
            ]])];
            return $context;
        };
        $this->router = new Hm_MCP_Router($this->site, $this->services);
    }

    private function request($method, $path, $token = null, $body = null, $headers = [], $query = []) {
        $headers = array_merge(['Host' => 'mail.example.com', 'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream'], $headers);
        if ($token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        $raw = is_string($body) ? $body : ($body === null ? '' : json_encode($body));
        return $this->router->handle(new Hm_MCP_Http_Request($method, $path, $query, $headers, $raw, ['REMOTE_ADDR' => '127.0.0.1']));
    }

    private function rpc($token, $method, $params, $session = null, $id = 1) {
        $headers = ['MCP-Protocol-Version' => '2025-06-18'];
        if ($session) {
            $headers['Mcp-Session-Id'] = $session;
        }
        $res = $this->request('POST', '/mcp', $token, ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params], $headers);
        return [$res, json_decode($res->body, true)];
    }

    private function initialize($token) {
        list($res, $data) = $this->rpc($token, 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => new stdClass(),
            'clientInfo' => ['name' => 'test', 'version' => '1']]);
        $this->assertSame(200, $res->status);
        $session = $res->header('Mcp-Session-Id');
        $this->assertNotEmpty($session);
        $this->request('POST', '/mcp', $token, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['Mcp-Session-Id' => $session, 'MCP-Protocol-Version' => '2025-06-18']);
        return [$session, $data];
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_initialize_list_and_call_tools() {
        list($session, $init) = $this->initialize($this->personal);
        $this->assertSame('2025-06-18', $init['result']['protocolVersion']);
        $this->assertSame('cypht', $init['result']['serverInfo']['name']);
        $this->assertStringContainsString('untrusted', $init['result']['instructions']);
        $this->assertArrayHasKey('tools', $init['result']['capabilities']);

        list($res, $data) = $this->rpc($this->personal, 'tools/list', new stdClass(), $session, 2);
        $tools = array_column($data['result']['tools'], null, 'name');
        $this->assertArrayHasKey('list_messages', $tools);
        $this->assertArrayHasKey('search', $tools);
        $this->assertArrayNotHasKey('nextCursor', $data['result']);
        $this->assertSame([['type' => 'oauth2', 'scopes' => ['mail.read']]], $tools['get_message']['securitySchemes']);
        $this->assertArrayHasKey('outputSchema', $tools['get_message']);

        list($res, $data) = $this->rpc($this->personal, 'tools/call', ['name' => 'list_messages', 'arguments' => ['limit' => 5]], $session, 3);
        $result = $data['result'];
        $this->assertFalse($result['isError']);
        $this->assertSame('Hello', $result['structuredContent']['data']['messages'][0]['subject']);
        $this->assertSame($result['structuredContent'], json_decode($result['content'][0]['text'], true));

        list($res, $data) = $this->rpc($this->personal, 'tools/call', ['name' => 'get_message',
            'arguments' => ['message_id' => 'msg_invalid_id']], $session, 4);
        $this->assertTrue($data['result']['isError']);
        $this->assertStringStartsWith('invalid_argument:', $data['result']['content'][0]['text']);

        $activity = $this->store->activity('alice');
        $this->assertSame(['get_message', 'list_messages'], array_column($activity, 'operation'));
        $this->assertSame(['error', 'ok'], array_column($activity, 'outcome'));
        $this->assertSame('mcp', $activity[0]['channel']);
        $this->assertGreaterThan(0, $this->store->connection($this->personal_id)['last_used_at']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_permission_changes_apply_to_the_next_request() {
        list($session) = $this->initialize($this->personal);
        $permissions = array_merge(Hm_MCP_Permissions::defaults(), ['read' => false]);
        $this->store->save_settings('alice', ['permissions' => $permissions]);
        list($res, $data) = $this->rpc($this->personal, 'tools/list', new stdClass(), $session, 2);
        $this->assertSame([], $data['result']['tools']);
        list($res, $data) = $this->rpc($this->personal, 'tools/call', ['name' => 'list_messages', 'arguments' => []], $session, 3);
        $this->assertArrayHasKey('error', $data);

        $this->store->save_settings('alice', ['permissions' => Hm_MCP_Permissions::defaults()]);
        $this->store->update_connection($this->personal_id, ['accounts' => ['other']]);
        list($res, $data) = $this->rpc($this->personal, 'tools/call', ['name' => 'list_accounts', 'arguments' => []], $session, 4);
        $this->assertSame([], $data['result']['structuredContent']['data']['accounts']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_disabled_access_and_reauth() {
        $this->store->save_settings('alice', ['enabled' => false]);
        $res = $this->request('POST', '/mcp', $this->personal, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);
        $this->assertSame(403, $res->status);
        $this->assertSame('access_disabled', $res->decoded()['error']['code']);
        $res = $this->request('GET', '/api/v1/accounts', $this->personal);
        $this->assertSame(403, $res->status);

        $this->store->save_settings('alice', ['enabled' => true]);
        $this->store->update_connection($this->personal_id, ['status' => 'reauth']);
        $res = $this->request('POST', '/mcp', $this->personal, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);
        $this->assertSame(401, $res->status);
        $this->assertStringContainsString('error="invalid_token"', $res->header('WWW-Authenticate'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_oauth_tokens_are_bound_to_the_mcp_resource() {
        list($oauth_id, $key) = $this->store->create_connection('alice', 'oauth', 'ChatGPT', 'pw', ['client_id' => 'cl_1']);
        $good = $this->store->issue_token($oauth_id, 'access', $key, 3600, ['resource' => 'https://mail.example.com/mcp']);
        $wrong = $this->store->issue_token($oauth_id, 'access', $key, 3600, ['resource' => 'https://other.example.com/mcp']);
        list($session) = $this->initialize($good);
        $res = $this->request('POST', '/mcp', $wrong, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);
        $this->assertSame(401, $res->status);
        /* OAuth access tokens are issued for /mcp, the REST API needs a personal token */
        $res = $this->request('GET', '/api/v1/accounts', $good);
        $this->assertSame(401, $res->status);
        /* a session belongs to the connection that created it */
        list($res, $data) = $this->rpc($this->personal, 'tools/list', new stdClass(), $session, 2);
        $this->assertSame(404, $res->status);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_rest_routes() {
        $res = $this->request('GET', '/api/v1');
        $this->assertSame(200, $res->status);
        $this->assertSame('https://mail.example.com/api/v1/openapi.json', $res->decoded()['openapi']);
        $res = $this->request('GET', '/api/v1/openapi.json');
        $this->assertSame('3.1.0', $res->decoded()['openapi']);

        $res = $this->request('GET', '/api/v1/messages');
        $this->assertSame(401, $res->status);
        $this->assertSame('Bearer realm="cypht"', $res->header('WWW-Authenticate'));

        $res = $this->request('GET', '/api/v1/messages', $this->personal, null, [], ['view' => 'inbox', 'limit' => '3']);
        $this->assertSame(200, $res->status);
        $this->assertSame('Hello', $res->decoded()['data']['messages'][0]['subject']);
        $id = $res->decoded()['data']['messages'][0]['id'];

        $res = $this->request('GET', '/api/v1/messages/'.rawurlencode($id), $this->personal);
        $this->assertSame(200, $res->status);
        $this->assertSame('Plain body of Hello', $res->decoded()['data']['body']['text']);

        $res = $this->request('POST', '/api/v1/tools/list_accounts', $this->personal, '{}');
        $this->assertSame(200, $res->status);
        $this->assertSame('acc1', $res->decoded()['data']['accounts'][0]['id']);

        $this->assertSame(405, $this->request('GET', '/api/v1/tools/list_accounts', $this->personal)->status);
        $this->assertSame(405, $this->request('POST', '/api/v1/accounts', $this->personal, '{}')->status);
        $this->assertSame(404, $this->request('GET', '/api/v1/nothing', $this->personal)->status);
        $this->assertSame(404, $this->request('POST', '/api/v1/tools/not_a_tool', $this->personal, '{}')->status);
        $this->assertSame(400, $this->request('POST', '/api/v1/tools/list_accounts', $this->personal, '[1,2]')->status);
        $bad = $this->request('GET', '/api/v1/messages', $this->personal, null, [], ['bogus' => '1']);
        $this->assertSame(400, $bad->status);
        $this->assertSame('invalid_argument', $bad->decoded()['error']['code']);
        $missing = $this->request('GET', '/api/v1/messages/'.rawurlencode(Hm_MCP_Format::message_id('acc1', 'INBOX', '99')), $this->personal);
        $this->assertSame(404, $missing->status);

        $this->store->save_settings('alice', ['permissions' => array_merge(Hm_MCP_Permissions::defaults(), ['read' => false])]);
        $denied = $this->request('GET', '/api/v1/accounts', $this->personal);
        $this->assertSame(403, $denied->status);
        $this->assertSame('permission_denied', $denied->decoded()['error']['code']);
        $this->assertSame('rest', $this->store->activity('alice')[0]['channel']);
        $this->assertSame('denied', $this->store->activity('alice')[0]['outcome']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_modern_protocol_era_is_stateless() {
        $meta = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => new stdClass()];
        $res = $this->request('POST', '/mcp', $this->personal, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'list_accounts', 'arguments' => new stdClass(), '_meta' => $meta]],
            ['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => 'list_accounts']);
        $this->assertSame(200, $res->status);
        $this->assertNull($res->header('Mcp-Session-Id'));
        $data = json_decode($res->body, true);
        $this->assertSame('acc1', $data['result']['structuredContent']['data']['accounts'][0]['id']);
    }
}
