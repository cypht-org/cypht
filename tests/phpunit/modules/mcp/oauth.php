<?php

use PHPUnit\Framework\TestCase;

require_once APP_PATH.'modules/mcp/hm-mcp.php';

/**
 * tests for the OAuth authorization server, run against the test database of the current engine
 */
class Hm_Test_MCP_OAuth extends TestCase {

    const REDIRECT = 'https://chatgpt.com/connector_platform_oauth_redirect';
    const RESOURCE = 'https://mail.example.com/mcp';
    /* RFC 7636 appendix B */
    const VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
    const PASSWORD = 'correct horse';

    private $config;
    private $services;
    private $store;
    private $router;
    private $user_config;
    private $now = 1800000000;

    public function setUp(): void {
        $this->config = new Hm_Mock_Config();
        $this->config->set('mcp_public_url', 'https://mail.example.com');
        $this->config->set('app_name', 'Test Mail');
        $this->config->set('default_language', 'en');
        setup_db($this->config);
        $this->services = new Hm_MCP_Services($this->config);
        $this->store = $this->services->store();
        if (!$this->store->available()) {
            $this->markTestSkipped('MCP tables are not available in the test database');
        }
        $this->store->clock = function () { return $this->now; };
        $dbh = Hm_DB::connect($this->config);
        foreach (['hm_mcp_settings', 'hm_mcp_connections', 'hm_mcp_tokens', 'hm_mcp_oauth_clients', 'hm_mcp_sessions', 'hm_mcp_activity', 'hm_mcp_rate_limits'] as $table) {
            $dbh->exec('delete from '.$table);
        }
        $this->user_config = new Hm_Mock_Config();
        $this->user_config->set('imap_servers', [
            'a1' => ['name' => 'Work', 'user' => 'alice@example.com', 'server' => 'imap.example.com'],
            'b2' => ['name' => 'Home', 'user' => 'alice@example.net', 'server' => 'imap.example.net'],
        ]);
        $oauth = $this->services->oauth();
        $oauth->credential_checker = function ($user, $pass) { return $user === 'alice' && $pass === self::PASSWORD; };
        $oauth->user_config_loader = function ($user, $pass) { return $this->user_config; };
        $this->store->save_settings('alice', ['enabled' => true, 'permissions' => Hm_MCP_Permissions::defaults(),
            'accounts' => ['mode' => 'all', 'ids' => []]]);
        $this->router = new Hm_MCP_Router($this->config, $this->services);
    }

    /* ------------------------------------------------------------ helpers */

    private function request($method, $path, $query = [], $form = [], $headers = [], $body = '') {
        return new Hm_MCP_Http_Request($method, $path, $query, array_merge(['Host' => 'mail.example.com'], $headers), $body,
            ['REMOTE_ADDR' => '203.0.113.7'], $form);
    }

    private function register($meta = null) {
        $meta = $meta ?? ['client_name' => 'ChatGPT', 'redirect_uris' => [self::REDIRECT],
            'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'], 'token_endpoint_auth_method' => 'none'];
        return $this->router->handle($this->request('POST', '/oauth/register', [], [], ['Content-Type' => 'application/json'],
            is_string($meta) ? $meta : json_encode($meta)));
    }

    private function client_id($meta = null) {
        $res = $this->register($meta);
        $this->assertSame(201, $res->status, $res->body);
        return $res->decoded()['client_id'];
    }

    private function params($client_id, $overrides = []) {
        return array_merge([
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => self::REDIRECT,
            'state' => 'st-123',
            'code_challenge' => Hm_MCP_Crypto::pkce_s256(self::VERIFIER),
            'code_challenge_method' => 'S256',
            'resource' => self::RESOURCE,
        ], $overrides);
    }

    private function authorize_get($params) {
        return $this->router->handle($this->request('GET', '/oauth/authorize', $params));
    }

    private function authorize_post($form, $headers = ['Origin' => 'https://mail.example.com', 'Sec-Fetch-Site' => 'same-origin']) {
        return $this->router->handle($this->request('POST', '/oauth/authorize', [], $form, $headers));
    }

    private function login($client_id, $overrides = [], $password = self::PASSWORD, $extra = []) {
        return $this->authorize_post(array_merge($this->params($client_id, $overrides),
            ['step' => 'login', 'decision' => 'allow', 'username' => 'alice', 'password' => $password], $extra));
    }

    private function ticket($response) {
        $this->assertSame(200, $response->status, $response->body);
        $this->assertSame(1, preg_match('/name="ticket" value="(cyp_ct_[A-Za-z0-9_-]+)"/', $response->body, $matches), 'no consent ticket');
        return $matches[1];
    }

    private function consent($ticket, $permissions = null, $accounts = null, $decision = 'allow') {
        return $this->authorize_post(['step' => 'consent', 'ticket' => $ticket, 'decision' => $decision,
            'permissions' => $permissions ?? ['read', 'organize', 'drafts'], 'accounts' => $accounts ?? ['a1', 'b2']]);
    }

    private function redirect_params($response, $redirect = self::REDIRECT) {
        $this->assertSame(302, $response->status, $response->body);
        $location = $response->header('Location');
        $this->assertStringStartsWith($redirect.'?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        return $query;
    }

    private function code($client_id, $permissions = null, $accounts = null, $overrides = []) {
        $query = $this->redirect_params($this->consent($this->ticket($this->login($client_id, $overrides)), $permissions, $accounts));
        $this->assertSame('https://mail.example.com', $query['iss']);
        return $query['code'];
    }

    private function token($form, $headers = []) {
        return $this->router->handle($this->request('POST', '/oauth/token', [], $form, $headers));
    }

    private function exchange($client_id, $code, $overrides = []) {
        return $this->token(array_merge(['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $client_id,
            'redirect_uri' => self::REDIRECT, 'code_verifier' => self::VERIFIER, 'resource' => self::RESOURCE], $overrides));
    }

    private function refresh($client_id, $refresh_token) {
        return $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $refresh_token, 'client_id' => $client_id,
            'resource' => self::RESOURCE]);
    }

    private function tokens($client_id, $permissions = null, $accounts = null) {
        $res = $this->exchange($client_id, $this->code($client_id, $permissions, $accounts));
        $this->assertSame(200, $res->status, $res->body);
        return $res->decoded();
    }

    private function revoke($form) {
        return $this->router->handle($this->request('POST', '/oauth/revoke', [], $form));
    }

    private function valid($token) {
        return $this->services->auth()->authenticate($token, ['access', 'personal'])['ok'];
    }

    private function connection_rows() {
        return (int) Hm_DB::connect($this->config)->query('select count(*) from hm_mcp_connections')->fetchColumn();
    }

    /* same algorithm as check_2fa_pin() */
    private static function totp($secret) {
        $time = str_pad(pack('N', floor(time() / 30)), 8, chr(0), STR_PAD_LEFT);
        $hash = hash_hmac('sha1', $time, $secret, true);
        $offset = ord(substr($hash, -1)) & 0xF;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /* ------------------------------------------------------------- metadata */

    public function test_authorization_server_metadata() {
        $prm = $this->router->handle($this->request('GET', '/.well-known/oauth-protected-resource/mcp'))->decoded();
        foreach (['/.well-known/oauth-authorization-server', '/.well-known/oauth-authorization-server/mcp'] as $path) {
            $res = $this->router->handle($this->request('GET', $path));
            $this->assertSame(200, $res->status);
            $data = $res->decoded();
            $this->assertSame('https://mail.example.com', $data['issuer']);
            $this->assertSame($prm['authorization_servers'][0], $data['issuer']);
            $this->assertSame('https://mail.example.com/oauth/authorize', $data['authorization_endpoint']);
            $this->assertSame('https://mail.example.com/oauth/token', $data['token_endpoint']);
            $this->assertSame('https://mail.example.com/oauth/register', $data['registration_endpoint']);
            $this->assertSame('https://mail.example.com/oauth/revoke', $data['revocation_endpoint']);
            $this->assertSame(['code'], $data['response_types_supported']);
            $this->assertSame(['authorization_code', 'refresh_token'], $data['grant_types_supported']);
            $this->assertSame(['S256'], $data['code_challenge_methods_supported']);
            $this->assertSame(['none'], $data['token_endpoint_auth_methods_supported']);
            $this->assertTrue($data['authorization_response_iss_parameter_supported']);
            $this->assertSame(Hm_MCP_Permissions::scopes(), $data['scopes_supported']);
            $this->assertNotContains('openid', $data['scopes_supported']);
            $this->assertSame('*', $res->header('Access-Control-Allow-Origin'));
        }
        $this->assertSame(405, $this->router->handle($this->request('POST', '/.well-known/oauth-authorization-server'))->status);
    }

    public function test_methods_are_checked() {
        foreach (['/oauth/register', '/oauth/token', '/oauth/revoke'] as $path) {
            $res = $this->router->handle($this->request('GET', $path));
            $this->assertSame(405, $res->status);
            $this->assertSame('POST, OPTIONS', $res->header('Allow'));
        }
        $this->assertSame(405, $this->router->handle($this->request('PUT', '/oauth/authorize'))->status);
        $res = $this->router->handle($this->request('OPTIONS', '/oauth/token'));
        $this->assertSame(204, $res->status);
        $this->assertSame('*', $res->header('Access-Control-Allow-Origin'));
    }

    /* --------------------------------------------------------- registration */

    public function test_registration_creates_a_public_client() {
        $res = $this->register(['client_name' => "Chat\u{200B}GPT\n", 'redirect_uris' => [self::REDIRECT, self::REDIRECT],
            'token_endpoint_auth_method' => 'client_secret_basic']);
        $this->assertSame(201, $res->status);
        $this->assertSame('no-store', $res->header('Cache-Control'));
        $data = $res->decoded();
        $this->assertMatchesRegularExpression('/^cl_[0-9a-f]{32}$/', $data['client_id']);
        $this->assertSame('none', $data['token_endpoint_auth_method']);
        $this->assertArrayNotHasKey('client_secret', $data);
        $this->assertSame('ChatGPT', $data['client_name']);
        $this->assertSame([self::REDIRECT], $data['redirect_uris']);
        $this->assertSame(['authorization_code', 'refresh_token'], $data['grant_types']);
        $this->assertSame(['code'], $data['response_types']);
        $this->assertSame($this->now, $data['client_id_issued_at']);
        $client = $this->store->client($data['client_id']);
        $this->assertSame('dcr', $client['kind']);
        $this->assertSame(0, $client['last_used_at']);

        $res = $this->register(['redirect_uris' => ['http://127.0.0.1:33418/callback', 'http://localhost/cb', 'http://[::1]:8080/cb',
            'cursor://anysphere.cursor-mcp/oauth/callback', 'com.example.app:/oauth2redirect']]);
        $this->assertSame(201, $res->status, $res->body);
        $this->assertSame('', $res->decoded()['client_name']);
    }

    public static function unsafe_redirect_uris() {
        return PolicyCases::forPolicy('mcp-oauth-redirect-safety');
    }

    /**
     * @dataProvider unsafe_redirect_uris
     */
    public function test_registration_rejects_unsafe_redirect_uris($case) {
        $res = $this->register(['client_name' => 'x', 'redirect_uris' => [$case['input']['redirect_uri']]]);
        $this->assertSame($case['expected']['status'], $res->status);
        $this->assertSame($case['expected']['error'], $res->decoded()['error']);
    }

    public function test_registration_rejects_invalid_metadata() {
        $cases = [
            ['{not json', 'invalid_client_metadata'],
            ['["https://chatgpt.com/cb"]', 'invalid_client_metadata'],
            ['', 'invalid_client_metadata'],
            [['client_name' => 'x'], 'invalid_redirect_uri'],
            [['redirect_uris' => []], 'invalid_redirect_uri'],
            [['redirect_uris' => array_fill(0, Hm_MCP_OAuth::MAX_REDIRECT_URIS + 1, self::REDIRECT)], 'invalid_redirect_uri'],
            [['redirect_uris' => [self::REDIRECT], 'response_types' => ['token']], 'invalid_client_metadata'],
            [['redirect_uris' => [self::REDIRECT], 'grant_types' => ['client_credentials']], 'invalid_client_metadata'],
        ];
        foreach ($cases as $case) {
            $res = $this->register($case[0]);
            $this->assertSame(400, $res->status, json_encode($case[0]));
            $this->assertSame($case[1], $res->decoded()['error'], json_encode($case[0]));
        }
        $res = $this->register(json_encode(['redirect_uris' => [self::REDIRECT], 'client_name' => str_repeat('x', Hm_MCP_OAuth::MAX_REGISTRATION_BYTES)]));
        $this->assertSame(413, $res->status);
    }

    public function test_registration_is_rate_limited_per_address() {
        for ($i = 0; $i < Hm_MCP_OAuth::REGISTER_ATTEMPTS; $i++) {
            $this->assertSame(201, $this->register()->status);
        }
        $res = $this->register();
        $this->assertSame(429, $res->status);
        $this->assertNotNull($res->header('Retry-After'));
    }

    /* fill a rate limit window */
    private function exhaust($key) {
        $this->store->rate_limit($key, 1, 3600);
        Hm_DB::execute(Hm_DB::connect($this->config), 'update hm_mcp_rate_limits set hits=? where rate_key=?', [100000, 'mcp:'.hash('sha256', $key)]);
    }

    public function test_totals_bound_forged_client_addresses() {
        /* every request claims another address */
        $this->config->set('mcp_client_ip_header', 'CF-Connecting-IP');
        $register = function ($ip) {
            return $this->router->handle($this->request('POST', '/oauth/register', [], [], ['Content-Type' => 'application/json',
                'CF-Connecting-IP' => $ip], json_encode(['client_name' => 'x', 'redirect_uris' => [self::REDIRECT]])));
        };
        $this->assertSame(201, $register('198.51.100.1')->status);
        $this->exhaust('register:*');
        $this->assertSame(429, $register('198.51.100.2')->status);

        $client_id = $this->client_id_from_store();
        $this->exhaust('login:*');
        $res = $this->authorize_post(array_merge($this->params($client_id), ['step' => 'login', 'decision' => 'allow',
            'username' => 'alice', 'password' => self::PASSWORD]), ['Origin' => 'https://mail.example.com', 'CF-Connecting-IP' => '198.51.100.3']);
        $this->assertSame(429, $res->status);
        $this->assertStringContainsString('Too many attempts', $res->body);
    }

    private function client_id_from_store() {
        $client = ['client_id' => 'cl_total', 'kind' => 'dcr', 'client_name' => 'Total', 'redirect_uris' => [self::REDIRECT]];
        $this->store->save_client($client);
        return 'cl_total';
    }

    /* -------------------------------------------------------- authorization */

    public function test_unknown_client_or_redirect_is_never_redirected() {
        $res = $this->authorize_get($this->params('cl_unknown'));
        $this->assertSame(400, $res->status);
        $this->assertNull($res->header('Location'));
        $this->assertStringContainsString('not registered', $res->body);

        $client_id = $this->client_id();
        foreach (['https://evil.example.com/cb', self::REDIRECT.'/extra', strtoupper(self::REDIRECT)] as $uri) {
            $res = $this->authorize_get($this->params($client_id, ['redirect_uri' => $uri]));
            $this->assertSame(400, $res->status, $uri);
            $this->assertNull($res->header('Location'));
            $this->assertStringContainsString('does not match its registration', $res->body);
        }
        /* the single registered redirect is used when none is given */
        $res = $this->authorize_get($this->params($client_id, ['redirect_uri' => '']));
        $this->assertSame(200, $res->status);
        $this->assertStringNotContainsString('name="redirect_uri"', $res->body);
    }

    public static function invalid_requests() {
        return [
            [['response_type' => 'token'], 'unsupported_response_type'],
            [['code_challenge' => ''], 'invalid_request'],
            [['code_challenge_method' => 'plain'], 'invalid_request'],
            [['code_challenge_method' => ''], 'invalid_request'],
            [['code_challenge' => 'too-short'], 'invalid_request'],
            [['resource' => 'https://other.example.com/mcp'], 'invalid_target'],
            [['resource' => 'https://mail.example.com'], 'invalid_target'],
        ];
    }

    /**
     * @dataProvider invalid_requests
     */
    public function test_invalid_requests_are_sent_back_with_the_issuer($overrides, $error) {
        $client_id = $this->client_id();
        $query = $this->redirect_params($this->authorize_get($this->params($client_id, $overrides)));
        $this->assertSame($error, $query['error']);
        $this->assertSame('st-123', $query['state']);
        $this->assertSame('https://mail.example.com', $query['iss']);
        $this->assertArrayNotHasKey('code', $query);
    }

    public function test_sign_in_page_is_escaped_and_locked_down() {
        $client_id = $this->client_id(['client_name' => '<script>alert(1)</script>', 'redirect_uris' => [self::REDIRECT]]);
        $res = $this->authorize_get($this->params($client_id, ['state' => '"><img src=x onerror=alert(1)>', 'resource' => self::RESOURCE.'/']));
        $this->assertSame(200, $res->status);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $res->body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $res->body);
        $this->assertStringNotContainsString('<img', $res->body);
        $this->assertStringContainsString('<strong>chatgpt.com</strong>', $res->body);
        $this->assertStringContainsString('lang="en"', $res->body);
        $this->assertStringContainsString('autocomplete="current-password"', $res->body);
        $this->assertStringNotContainsString('name="code"', $res->body);
        $csp = $res->header('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("form-action 'self' https://chatgpt.com;", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertSame('no-store', $res->header('Cache-Control'));
        $this->assertSame('DENY', $res->header('X-Frame-Options'));
        $this->assertSame('same-origin', $res->header('Referrer-Policy'));
        $this->assertNull($res->header('Access-Control-Allow-Origin'));
    }

    public function test_pages_follow_the_site_and_user_language() {
        $client_id = $this->client_id();
        $this->config->set('default_language', 'es');
        $res = $this->authorize_get($this->params($client_id));
        $this->assertStringContainsString('lang="es"', $res->body);
        $this->assertStringContainsString('Inicia sesión para conectar una aplicación', $res->body);
        $this->user_config->set('language_setting', 'en');
        $res = $this->login($client_id);
        $this->ticket($res);
        $this->assertStringContainsString('lang="en"', $res->body);
        $this->assertStringContainsString('Connect an app to your mail', $res->body);
    }

    public function test_wrong_password_or_disabled_access_creates_nothing() {
        $client_id = $this->client_id();
        $res = $this->login($client_id, [], 'n0t-the-password');
        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('Invalid username or password', $res->body);
        $this->assertStringContainsString('value="alice"', $res->body);
        $this->assertStringNotContainsString('n0t-the-password', $res->body);
        $this->assertStringNotContainsString('name="ticket"', $res->body);
        $res = $this->login($client_id, [], '');
        $this->assertStringContainsString('Enter your username and password.', $res->body);

        $settings = $this->store->settings('alice');
        $settings['enabled'] = false;
        $this->store->save_settings('alice', $settings);
        $res = $this->login($client_id);
        $this->assertStringContainsString('API and MCP access is turned off', $res->body);
        $this->assertStringNotContainsString('name="ticket"', $res->body);
        $this->assertSame(0, $this->connection_rows());
    }

    public function test_settings_that_cannot_be_decrypted_stop_the_sign_in() {
        $client_id = $this->client_id();
        $this->services->oauth()->user_config_loader = function () { return false; };
        $res = $this->login($client_id);
        $this->assertStringContainsString('could not be opened', $res->body);
        $this->assertSame(0, $this->connection_rows());
    }

    public function test_cross_site_form_posts_are_blocked() {
        $client_id = $this->client_id();
        $form = array_merge($this->params($client_id), ['step' => 'login', 'decision' => 'allow', 'username' => 'alice', 'password' => self::PASSWORD]);
        foreach ([['Origin' => 'https://evil.example.net'], ['Origin' => 'null'], ['Sec-Fetch-Site' => 'cross-site'], ['Sec-Fetch-Site' => 'same-site']] as $headers) {
            $res = $this->authorize_post($form, $headers);
            $this->assertSame(403, $res->status, json_encode($headers));
            $this->assertStringContainsString('another site', $res->body);
        }
        $this->assertSame(0, $this->connection_rows());
        /* older browsers send neither header */
        $this->ticket($this->authorize_post($form, []));
    }

    public function test_cancel_on_the_sign_in_page() {
        $client_id = $this->client_id();
        $query = $this->redirect_params($this->authorize_post(array_merge($this->params($client_id), ['step' => 'login', 'decision' => 'deny'])));
        $this->assertSame('access_denied', $query['error']);
        $this->assertSame('st-123', $query['state']);
        $this->assertSame('https://mail.example.com', $query['iss']);
    }

    public function test_sign_in_is_rate_limited() {
        $client_id = $this->client_id();
        for ($i = 0; $i < Hm_MCP_OAuth::LOGIN_ATTEMPTS; $i++) {
            $this->login($client_id, [], 'bad password');
        }
        $res = $this->login($client_id);
        $this->assertSame(429, $res->status);
        $this->assertStringContainsString('Too many attempts', $res->body);
        $this->now += Hm_MCP_OAuth::LOGIN_WINDOW + 1;
        $this->ticket($this->login($client_id));
    }

    public function test_full_flow_follows_the_global_settings() {
        $client_id = $this->client_id();
        $res = $this->login($client_id);
        $ticket = $this->ticket($res);
        $this->assertStringContainsString('value="read" checked', $res->body);
        $this->assertStringContainsString('value="drafts" checked', $res->body);
        $this->assertStringNotContainsString('value="send"', $res->body);
        $this->assertStringContainsString('alice@example.com', $res->body);
        $this->assertStringContainsString('<strong>alice</strong>', $res->body);
        $this->assertStringNotContainsString(self::PASSWORD, $res->body);
        $this->assertStringContainsString("form-action 'self' https://chatgpt.com;", $res->header('Content-Security-Policy'));
        /* pending until the code is exchanged */
        $this->assertSame([], $this->store->connections('alice'));
        $this->assertSame(1, $this->connection_rows());

        $redirect = $this->consent($ticket);
        $this->assertSame('no-referrer', $redirect->header('Referrer-Policy'));
        $query = $this->redirect_params($redirect);
        $this->assertSame('st-123', $query['state']);
        $this->assertSame('https://mail.example.com', $query['iss']);
        $this->assertStringStartsWith('cyp_ac_', $query['code']);

        $res = $this->exchange($client_id, $query['code']);
        $this->assertSame(200, $res->status, $res->body);
        $this->assertSame('no-store', $res->header('Cache-Control'));
        $this->assertSame('no-cache', $res->header('Pragma'));
        $data = $res->decoded();
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertSame(3600, $data['expires_in']);
        $this->assertStringStartsWith('cyp_at_', $data['access_token']);
        $this->assertStringStartsWith('cyp_rt_', $data['refresh_token']);
        $this->assertSame('mail.read mail.organize mail.drafts', $data['scope']);

        $connections = $this->store->connections('alice');
        $this->assertCount(1, $connections);
        $this->assertSame('oauth', $connections[0]['kind']);
        $this->assertSame('ChatGPT', $connections[0]['name']);
        $this->assertSame($client_id, $connections[0]['client_id']);
        $this->assertNull($connections[0]['permissions']);
        $this->assertNull($connections[0]['accounts']);
        $auth = $this->services->auth()->authenticate($data['access_token'], ['access', 'personal']);
        $this->assertTrue($auth['ok']);
        $this->assertSame('alice', $auth['principal']->username);
        $this->assertSame(self::RESOURCE, $auth['token']['data']['resource']);
        $this->assertSame(self::PASSWORD, $this->store->open_password($connections[0], $auth['principal']->key));
        $this->assertFalse($auth['principal']->can('send'));

        /* the connection follows later changes of the global settings */
        $settings = $this->store->settings('alice');
        $settings['permissions']['send'] = true;
        $this->store->save_settings('alice', $settings);
        $this->assertTrue($this->services->auth()->authenticate($data['access_token'], ['access'])['principal']->can('send'));

        /* used clients are never removed by the cleanup */
        $this->assertSame($this->now, $this->store->client($client_id)['last_used_at']);
        $activity = $this->store->activity('alice');
        $this->assertSame('authorize', $activity[0]['operation']);
        $this->assertSame('oauth', $activity[0]['channel']);
        $this->assertSame('ok', $activity[0]['outcome']);
        $this->assertSame(['client' => 'ChatGPT', 'permissions' => 'global', 'accounts' => 'global'], $activity[0]['summary']);
    }

    public function test_consent_can_narrow_permissions_and_accounts() {
        $client_id = $this->client_id();
        $data = $this->tokens($client_id, ['read', 'send'], ['b2', 'unknown']);
        $this->assertSame('mail.read', $data['scope']);
        $connection = $this->store->connections('alice')[0];
        $this->assertTrue($connection['permissions']['read']);
        $this->assertFalse($connection['permissions']['organize']);
        $this->assertFalse($connection['permissions']['send']);
        $this->assertSame(['b2'], $connection['accounts']);
        /* the global settings stay the limit */
        $settings = $this->store->settings('alice');
        $settings['permissions']['read'] = false;
        $this->store->save_settings('alice', $settings);
        $this->assertFalse($this->services->auth()->authenticate($data['access_token'], ['access'])['principal']->can('read'));
    }

    public function test_requested_scope_limits_what_is_offered() {
        $client_id = $this->client_id();
        $res = $this->login($client_id, ['scope' => 'mail.read openid offline_access']);
        $ticket = $this->ticket($res);
        $this->assertStringContainsString('value="read"', $res->body);
        $this->assertStringNotContainsString('value="organize"', $res->body);
        $query = $this->redirect_params($this->consent($ticket, ['read', 'organize']));
        $data = $this->exchange($client_id, $query['code'])->decoded();
        $this->assertSame('mail.read', $data['scope']);
        $connection = $this->store->connections('alice')[0];
        $this->assertSame(['read'], array_keys(array_filter($connection['permissions'])));
        /* unknown scopes alone ask for everything */
        $this->assertSame(Hm_MCP_OAuth::requested_scope(''), Hm_MCP_OAuth::requested_scope('openid email'));
    }

    public function test_consent_validation_and_single_use_ticket() {
        $client_id = $this->client_id();
        $ticket = $this->ticket($this->login($client_id));
        $res = $this->consent($ticket, [], ['a1']);
        $this->assertSame(200, $res->status);
        $this->assertStringContainsString('Select at least one permission', $res->body);
        $res = $this->consent($ticket, ['read'], ['nope']);
        $this->assertStringContainsString('Select at least one account', $res->body);
        $this->assertStringContainsString('value="read" checked', $res->body);
        $this->assertStringNotContainsString('value="organize" checked', $res->body);
        $this->redirect_params($this->consent($ticket));
        $res = $this->consent($ticket);
        $this->assertSame(400, $res->status);
        $this->assertStringContainsString('expired', $res->body);
        $this->assertSame(400, $this->consent('cyp_ct_'.str_repeat('A', 43))->status);
    }

    public function test_deny_or_timeout_on_the_consent_page() {
        $client_id = $this->client_id();
        $query = $this->redirect_params($this->consent($this->ticket($this->login($client_id)), null, null, 'deny'));
        $this->assertSame('access_denied', $query['error']);
        $this->assertSame('st-123', $query['state']);
        $this->assertSame('https://mail.example.com', $query['iss']);
        $this->assertSame(0, $this->connection_rows());

        $ticket = $this->ticket($this->login($client_id));
        $this->now += Hm_MCP_OAuth::CONSENT_TTL + 1;
        $this->assertSame(400, $this->consent($ticket)->status);
    }

    /* ---------------------------------------------------------------- tokens */

    public function test_code_reuse_revokes_the_connection() {
        $client_id = $this->client_id();
        $code = $this->code($client_id);
        $first = $this->exchange($client_id, $code);
        $this->assertSame(200, $first->status);
        $second = $this->exchange($client_id, $code);
        $this->assertSame(400, $second->status);
        $this->assertSame('invalid_grant', $second->decoded()['error']);
        $this->assertFalse($this->valid($first->decoded()['access_token']));
        $this->assertSame(0, $this->connection_rows());
        $activity = $this->store->activity('alice');
        $this->assertSame('revoke', $activity[0]['operation']);
        $this->assertSame('denied', $activity[0]['outcome']);
        $this->assertSame(['reason' => 'code_reuse'], $activity[0]['summary']);
    }

    public static function invalid_exchanges() {
        return PolicyCases::forPolicy('mcp-oauth-code-binding');
    }

    /**
     * @dataProvider invalid_exchanges
     */
    public function test_code_exchange_checks($case) {
        $client_id = $this->client_id();
        $res = $this->exchange($client_id, $this->code($client_id), $case['input']['overrides']);
        $this->assertSame($case['expected']['status'], $res->status, $res->body);
        $this->assertSame($case['expected']['error'], $res->decoded()['error']);
        $this->assertSame('no-store', $res->header('Cache-Control'));
        $this->assertSame([], $this->store->connections('alice'));
    }

    public function test_code_is_bound_to_its_client_and_expires() {
        $client_id = $this->client_id();
        $other = $this->client_id();
        $res = $this->exchange($other, $this->code($client_id), ['client_id' => $other]);
        $this->assertSame('invalid_grant', $res->decoded()['error']);
        $code = $this->code($client_id);
        $this->now += Hm_MCP_OAuth::CODE_TTL + 1;
        $this->assertSame('invalid_grant', $this->exchange($client_id, $code)->decoded()['error']);
    }

    public function test_client_can_authenticate_with_http_basic() {
        $client_id = $this->client_id();
        $code = $this->code($client_id);
        $res = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::REDIRECT,
            'code_verifier' => self::VERIFIER], ['Authorization' => 'Basic '.base64_encode($client_id.':')]);
        $this->assertSame(200, $res->status, $res->body);
        $res = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => 'x', 'client_id' => 'cl_other'],
            ['Authorization' => 'Basic '.base64_encode($client_id.':')]);
        $this->assertSame('invalid_request', $res->decoded()['error']);
    }

    public function test_refresh_rotation_and_replay_detection() {
        $client_id = $this->client_id();
        $tokens = $this->tokens($client_id);
        $res = $this->refresh($client_id, $tokens['refresh_token']);
        $this->assertSame(200, $res->status, $res->body);
        $new = $res->decoded();
        $this->assertNotSame($tokens['refresh_token'], $new['refresh_token']);
        $this->assertNotSame($tokens['access_token'], $new['access_token']);
        $this->assertTrue($this->valid($new['access_token']));
        /* a retry right after the rotation is accepted (parallel refresh or lost response) */
        $this->assertSame(200, $this->refresh($client_id, $tokens['refresh_token'])->status);
        /* later it is a replay of a used token: the connection ends */
        $this->now += Hm_MCP_OAuth::REFRESH_GRACE + 1;
        $res = $this->refresh($client_id, $tokens['refresh_token']);
        $this->assertSame(400, $res->status);
        $this->assertSame('invalid_grant', $res->decoded()['error']);
        $this->assertFalse($this->valid($new['access_token']));
        $this->assertSame('invalid_grant', $this->refresh($client_id, $new['refresh_token'])->decoded()['error']);
        $this->assertSame(0, $this->connection_rows());
        $this->assertSame(['reason' => 'refresh_reuse'], $this->store->activity('alice')[0]['summary']);
    }

    public function test_refresh_is_bound_to_the_client_and_access_tokens_expire() {
        $client_id = $this->client_id();
        $other = $this->client_id();
        $tokens = $this->tokens($client_id);
        $this->assertSame('invalid_grant', $this->refresh($other, $tokens['refresh_token'])->decoded()['error']);
        $this->assertSame('invalid_grant', $this->refresh($client_id, $tokens['access_token'])->decoded()['error']);
        $this->now += 3601;
        $this->assertFalse($this->valid($tokens['access_token']));
        $res = $this->refresh($client_id, $tokens['refresh_token']);
        $this->assertSame(200, $res->status);
        $this->assertTrue($this->valid($res->decoded()['access_token']));
    }

    public function test_refresh_after_a_password_change_requires_a_new_connection() {
        $client_id = $this->client_id();
        $tokens = $this->tokens($client_id);
        $connection = $this->store->connections('alice')[0];
        $this->store->update_connection($connection['id'], ['status' => 'reauth']);
        $res = $this->refresh($client_id, $tokens['refresh_token']);
        $this->assertSame('invalid_grant', $res->decoded()['error']);
        $this->assertStringContainsString('password changed', $res->decoded()['error_description']);
        $this->assertSame(0, $this->connection_rows());
        $this->assertSame(['reason' => 'password_changed'], $this->store->activity('alice')[0]['summary']);
    }

    public function test_revocation() {
        $client_id = $this->client_id();
        $tokens = $this->tokens($client_id);
        $res = $this->revoke(['token' => 'cyp_rt_'.str_repeat('C', 43), 'client_id' => $client_id]);
        $this->assertSame(200, $res->status);
        $this->assertSame('', $res->body);
        /* another client cannot revoke the tokens */
        $this->revoke(['token' => $tokens['access_token'], 'client_id' => 'cl_other']);
        $this->assertTrue($this->valid($tokens['access_token']));
        /* an access token alone */
        $this->assertSame(200, $this->revoke(['token' => $tokens['access_token'], 'client_id' => $client_id, 'token_type_hint' => 'access_token'])->status);
        $this->assertFalse($this->valid($tokens['access_token']));
        $this->assertCount(1, $this->store->connections('alice'));
        /* the refresh token ends the connection */
        $this->assertSame(200, $this->revoke(['token' => $tokens['refresh_token'], 'client_id' => $client_id])->status);
        $this->assertSame(0, $this->connection_rows());
        $activity = $this->store->activity('alice');
        $this->assertSame('revoke', $activity[0]['operation']);
        $this->assertSame('ok', $activity[0]['outcome']);
        $this->assertSame(['reason' => 'client'], $activity[0]['summary']);
    }

    /* ------------------------------------------------------------- redirects */

    public function test_loopback_and_app_scheme_redirects() {
        $client_id = $this->client_id(['client_name' => 'CLI', 'redirect_uris' => ['http://127.0.0.1:33418/callback',
            'cursor://anysphere.cursor-mcp/oauth/callback']]);
        $res = $this->authorize_get($this->params($client_id, ['redirect_uri' => 'http://127.0.0.1:50123/callback']));
        $this->assertSame(200, $res->status);
        $this->assertStringContainsString("form-action 'self' http://127.0.0.1:50123;", $res->header('Content-Security-Policy'));
        $this->assertStringContainsString('this computer (127.0.0.1:50123)', $res->body);
        $this->assertSame(400, $this->authorize_get($this->params($client_id, ['redirect_uri' => 'http://127.0.0.1:50123/other']))->status);
        $this->assertSame(400, $this->authorize_get($this->params($client_id, ['redirect_uri' => 'http://localhost:50123/callback']))->status);
        /* two registered redirects: the client must choose */
        $res = $this->authorize_get($this->params($client_id, ['redirect_uri' => '']));
        $this->assertSame(400, $res->status);
        $this->assertStringContainsString('did not say where to return', $res->body);

        $uri = 'cursor://anysphere.cursor-mcp/oauth/callback';
        $res = $this->authorize_get($this->params($client_id, ['redirect_uri' => $uri]));
        $this->assertStringContainsString("form-action 'self' cursor:;", $res->header('Content-Security-Policy'));
        $this->assertStringContainsString('the cursor app', $res->body);
        $query = $this->redirect_params($this->consent($this->ticket($this->login($client_id, ['redirect_uri' => $uri]))), $uri);
        $res = $this->exchange($client_id, $query['code'], ['redirect_uri' => $uri]);
        $this->assertSame(200, $res->status, $res->body);
    }

    public function test_redirect_matching_rules() {
        $this->assertTrue(Hm_MCP_OAuth::redirect_matches(self::REDIRECT, self::REDIRECT));
        $this->assertFalse(Hm_MCP_OAuth::redirect_matches(self::REDIRECT, self::REDIRECT.'?x=1'));
        $this->assertTrue(Hm_MCP_OAuth::redirect_matches('http://127.0.0.1/cb', 'http://127.0.0.1:4000/cb'));
        $this->assertTrue(Hm_MCP_OAuth::redirect_matches('http://[::1]:80/cb', 'http://[::1]:81/cb'));
        $this->assertFalse(Hm_MCP_OAuth::redirect_matches('http://127.0.0.1/cb', 'http://127.0.0.2:4000/cb'));
        $this->assertFalse(Hm_MCP_OAuth::redirect_matches('https://app.example.com/cb', 'https://app.example.com:444/cb'));
        $this->assertFalse(Hm_MCP_OAuth::redirect_matches('http://127.0.0.1/cb', 'http://user@127.0.0.1:4000/cb'));
    }

    /* ------------------------------------------------- client metadata (CIMD) */

    /* the document ChatGPT publishes */
    const CHATGPT_CLIENT = '{"client_id":"https://chatgpt.com/oauth/client.json","client_uri":"https://chatgpt.com/","redirect_uris":["https://chatgpt.com/connector_platform_oauth_redirect"],"token_endpoint_auth_method":"private_key_jwt","token_endpoint_auth_methods_supported":["none","private_key_jwt"],"grant_types":["authorization_code","refresh_token"],"response_types":["code"],"client_name":"ChatGPT","logo_uri":"https://persistent.oaistatic.com/sonic/misc/openai-logo.png","token_endpoint_auth_signing_alg":"RS256","jwks_uri":"https://chatgpt.com/oauth/jwks.json"}';
    const CHATGPT_ID = 'https://chatgpt.com/oauth/client.json';

    private $fetches = [];

    private function enable_cimd($document = null, $status = 200, $ips = ['104.18.32.47']) {
        $this->config->set('mcp_cimd_allowed_hosts', 'chatgpt.com');
        $this->services->upload_resolver = function ($host) use ($ips) { return $ips; };
        $this->services->upload_fetcher = function ($url, $host, $ip, $max, $timeout) use (&$document, $status) {
            $this->fetches[] = $url;
            return [$status, ['content-type' => 'application/json; charset=utf-8'], $document ?? self::CHATGPT_CLIENT];
        };
    }

    private function failing_fetches() {
        $this->services->upload_fetcher = function ($url, $host, $ip, $max, $timeout) {
            $this->fetches[] = $url;
            throw new Hm_MCP_Error('upstream_error', 'down');
        };
    }

    public function test_cimd_is_advertised_only_when_hosts_are_allowed() {
        $data = $this->router->handle($this->request('GET', '/.well-known/oauth-authorization-server'))->decoded();
        $this->assertArrayNotHasKey('client_id_metadata_document_supported', $data);
        $this->config->set('mcp_cimd_allowed_hosts', 'chatgpt.com');
        $data = $this->router->handle($this->request('GET', '/.well-known/oauth-authorization-server'))->decoded();
        $this->assertTrue($data['client_id_metadata_document_supported']);
        $this->assertSame('https://mail.example.com/oauth/register', $data['registration_endpoint']);
    }

    public function test_cimd_client_ids() {
        $this->assertTrue(Hm_MCP_OAuth::is_cimd_id(self::CHATGPT_ID));
        $this->assertTrue(Hm_MCP_OAuth::is_cimd_id('https://chatgpt.com/oauth/abc123/client.json'));
        foreach (['http://chatgpt.com/oauth/client.json', 'https://chatgpt.com', 'https://chatgpt.com/', 'https://chatgpt.com/a/../client.json',
            'https://chatgpt.com/./client.json', 'https://user@chatgpt.com/client.json', 'https://chatgpt.com/client.json#x',
            'https://chatgpt.com/'.str_repeat('a', 250), 'https://chatgpt.com/a b', 'cl_0123', ''] as $id) {
            $this->assertFalse(Hm_MCP_OAuth::is_cimd_id($id), $id);
        }
    }

    public function test_chatgpt_connects_with_its_metadata_document() {
        $this->enable_cimd();
        $res = $this->authorize_get($this->params(self::CHATGPT_ID));
        $this->assertSame(200, $res->status, $res->body);
        $this->assertStringContainsString('<strong>ChatGPT</strong> wants to use your mail.', $res->body);
        $this->assertSame([self::CHATGPT_ID], $this->fetches);
        $client = $this->store->client(self::CHATGPT_ID);
        $this->assertSame(['cimd', 'ChatGPT', ['https://chatgpt.com/connector_platform_oauth_redirect']],
            [$client['kind'], $client['client_name'], $client['redirect_uris']]);
        $this->assertSame($this->now, $client['metadata']['fetched_at']);

        /* ChatGPT authenticates as a public client: "none" is in its supported methods */
        $data = $this->tokens(self::CHATGPT_ID);
        $this->assertStringStartsWith('cyp_at_', $data['access_token']);
        $this->assertCount(1, $this->fetches);
        $connection = $this->store->connections('alice')[0];
        $this->assertSame([self::CHATGPT_ID, 'ChatGPT'], [$connection['client_id'], $connection['name']]);

        /* the stored copy is used, then fetched again when it is old */
        $this->assertSame(200, $this->authorize_get($this->params(self::CHATGPT_ID))->status);
        $this->assertCount(1, $this->fetches);
        $this->now += Hm_MCP_OAuth::CIMD_TTL + 1;
        $this->assertSame(200, $this->authorize_get($this->params(self::CHATGPT_ID))->status);
        $this->assertCount(2, $this->fetches);

        /* an outage of the publisher: the old copy is kept for a while */
        $this->failing_fetches();
        $this->now += Hm_MCP_OAuth::CIMD_TTL + 1;
        $this->assertSame(200, $this->authorize_get($this->params(self::CHATGPT_ID))->status);
        $this->assertSame(200, $this->refresh(self::CHATGPT_ID, $data['refresh_token'])->status);
        $this->now += Hm_MCP_OAuth::CIMD_MAX_STALE;
        $res = $this->authorize_get($this->params(self::CHATGPT_ID));
        $this->assertSame(400, $res->status);
        $this->assertStringContainsString('could not be loaded', $res->body);
        $this->assertNull($res->header('Location'));
    }

    public static function rejected_documents() {
        $doc = json_decode(self::CHATGPT_CLIENT, true);
        return [
            'other client id' => [array_merge($doc, ['client_id' => 'https://chatgpt.com/other.json'])],
            'only private_key_jwt' => [array_merge($doc, ['token_endpoint_auth_methods_supported' => ['private_key_jwt']])],
            'singular private_key_jwt' => [array_diff_key($doc, ['token_endpoint_auth_methods_supported' => 1])],
            'no redirect uris' => [array_diff_key($doc, ['redirect_uris' => 1])],
            'unsafe redirect uri' => [array_merge($doc, ['redirect_uris' => ['javascript:alert(1)']])],
            'no code grant' => [array_merge($doc, ['grant_types' => ['client_credentials']])],
            'list' => [[$doc]],
            'not json' => ['<html>'],
        ];
    }

    /**
     * @dataProvider rejected_documents
     */
    public function test_invalid_metadata_documents_are_rejected($document) {
        $this->enable_cimd(is_string($document) ? $document : json_encode($document));
        $res = $this->authorize_get($this->params(self::CHATGPT_ID));
        $this->assertSame(400, $res->status);
        $this->assertStringContainsString('could not be loaded', $res->body);
        $this->assertNull($res->header('Location'));
        $this->assertFalse($this->store->client(self::CHATGPT_ID));
    }

    public function test_metadata_documents_are_only_fetched_safely() {
        /* CIMD off: nothing is fetched */
        $this->services->upload_fetcher = function () { $this->fetches[] = 'called'; return [200, [], self::CHATGPT_CLIENT]; };
        $res = $this->authorize_get($this->params(self::CHATGPT_ID));
        $this->assertStringContainsString('not registered', $res->body);
        $this->assertSame([], $this->fetches);

        $this->enable_cimd();
        foreach (['https://evil.example.com/client.json', 'https://chatgpt.com.evil.example/client.json', 'http://chatgpt.com/oauth/client.json'] as $id) {
            $res = $this->authorize_get($this->params($id));
            $this->assertSame(400, $res->status, $id);
            $this->assertStringContainsString('not registered', $res->body, $id);
        }
        $this->assertSame([], $this->fetches);
        /* the redirect must be one the document lists */
        $res = $this->authorize_get($this->params(self::CHATGPT_ID, ['redirect_uri' => 'https://chatgpt.com/other']));
        $this->assertSame(400, $res->status);
        $this->assertStringContainsString('does not match its registration', $res->body);
        /* the token endpoint never fetches a client it has not seen */
        $res = $this->exchange('https://chatgpt.com/oauth/new/client.json', 'cyp_ac_'.str_repeat('A', 43));
        $this->assertSame('invalid_client', $res->decoded()['error']);
        $this->assertCount(1, $this->fetches);
    }

    public function test_private_addresses_and_errors_are_refused() {
        $this->enable_cimd(null, 200, ['10.0.0.5']);
        $this->assertStringContainsString('could not be loaded', $this->authorize_get($this->params(self::CHATGPT_ID))->body);
        $this->assertSame([], $this->fetches);
        $this->enable_cimd(null, 302);
        $this->assertStringContainsString('could not be loaded', $this->authorize_get($this->params(self::CHATGPT_ID))->body);
        $this->enable_cimd(null, 404);
        $this->assertStringContainsString('could not be loaded', $this->authorize_get($this->params(self::CHATGPT_ID))->body);
        $this->assertCount(2, $this->fetches);
    }

    public function test_metadata_fetches_have_a_total_limit() {
        $this->enable_cimd();
        $this->exhaust('cimd:*');
        $res = $this->authorize_get($this->params(self::CHATGPT_ID));
        $this->assertStringContainsString('could not be loaded', $res->body);
        $this->assertSame([], $this->fetches);
    }

    public function test_metadata_fetches_are_rate_limited() {
        $this->enable_cimd(null, 404);
        for ($i = 0; $i < Hm_MCP_OAuth::CIMD_FETCHES + 5; $i++) {
            $this->authorize_get($this->params('https://chatgpt.com/oauth/'.$i.'/client.json'));
        }
        $this->assertCount(Hm_MCP_OAuth::CIMD_FETCHES, $this->fetches);
    }

    /* ------------------------------------------------------- two-factor auth */

    public function test_two_factor_code_is_required_when_turned_on() {
        $this->config->mods = ['2fa'];
        $this->config->set('2fa_secret', 'site two factor secret');
        $this->user_config->set('2fa_enable_setting', true);
        $this->user_config->set('2fa_backup_codes_setting', [123456789]);
        $client_id = $this->client_id();
        $this->assertStringContainsString('name="code"', $this->authorize_get($this->params($client_id))->body);

        $res = $this->login($client_id);
        $this->assertStringContainsString('Enter the code from your authenticator app.', $res->body);
        $this->assertStringNotContainsString('name="ticket"', $res->body);
        $res = $this->login($client_id, [], self::PASSWORD, ['code' => 'abcdef']);
        $this->assertStringContainsString('does not match', $res->body);
        $this->ticket($this->login($client_id, [], self::PASSWORD, ['code' => '123456789']));

        require_once APP_PATH.'modules/2fa/modules.php';
        $pin = self::totp(create_secret('site two factor secret', 'alice', 64));
        $this->ticket($this->login($client_id, [], self::PASSWORD, ['code' => substr($pin, 0, 3).' '.substr($pin, 3)]));

        /* users without two-factor authentication are not asked for a code */
        $this->user_config->set('2fa_enable_setting', false);
        $this->ticket($this->login($client_id));
    }
}
