<?php

use PHPUnit\Framework\TestCase;

require_once APP_PATH.'modules/mcp/hm-mcp.php';

/**
 * tests for the mcp module router
 */
class Hm_Test_MCP_Router extends TestCase {

    private function config($url = 'https://mail.example.com') {
        $config = new Hm_Mock_Config();
        $config->set('mcp_public_url', $url);
        $config->set('app_name', 'Test Mail');
        return $config;
    }

    private function request($method, $path, $headers = [], $body = '') {
        return new Hm_MCP_Http_Request($method, $path, [], array_merge(['Host' => 'mail.example.com'], $headers), $body,
            ['REMOTE_ADDR' => '127.0.0.1']);
    }

    public function test_not_configured() {
        $router = new Hm_MCP_Router($this->config(''));
        $res = $router->handle($this->request('GET', '/mcp'));
        $this->assertSame(503, $res->status);
        $this->assertSame('not_configured', $res->decoded()['error']['code']);
    }

    public function test_public_url_must_be_https_or_loopback() {
        $this->assertTrue(Hm_MCP_Config::valid_public_url('https://mail.example.com'));
        $this->assertTrue(Hm_MCP_Config::valid_public_url('http://localhost:8080'));
        $this->assertFalse(Hm_MCP_Config::valid_public_url('http://mail.example.com'));
        $this->assertFalse(Hm_MCP_Config::valid_public_url('https://user:pass@mail.example.com'));
        $this->assertFalse(Hm_MCP_Config::valid_public_url('https://mail.example.com/?x=1'));
        $this->assertFalse(Hm_MCP_Config::valid_public_url('mail.example.com'));
    }

    public function test_rejects_unknown_host() {
        $router = new Hm_MCP_Router($this->config());
        $res = $router->handle($this->request('GET', '/mcp', ['Host' => 'evil.example.net']));
        $this->assertSame(403, $res->status);
        $res = $router->handle($this->request('GET', '/mcp', ['Host' => '127.0.0.1:8080']));
        $this->assertSame(401, $res->status);
    }

    public function test_extra_allowed_hosts() {
        $config = $this->config();
        $config->set('mcp_allowed_hosts', 'internal.lan, other.example.com');
        $router = new Hm_MCP_Router($config);
        $res = $router->handle($this->request('GET', '/mcp', ['Host' => 'internal.lan']));
        $this->assertSame(401, $res->status);
    }

    public function test_protected_resource_metadata() {
        $router = new Hm_MCP_Router($this->config());
        foreach (['/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/mcp'] as $path) {
            $res = $router->handle($this->request('GET', $path));
            $this->assertSame(200, $res->status);
            $data = $res->decoded();
            $this->assertSame('https://mail.example.com/mcp', $data['resource']);
            $this->assertSame(['https://mail.example.com'], $data['authorization_servers']);
            $this->assertContains('mail.read', $data['scopes_supported']);
            $this->assertSame(['header'], $data['bearer_methods_supported']);
            $this->assertSame('*', $res->header('Access-Control-Allow-Origin'));
        }
        $res = $router->handle($this->request('POST', '/.well-known/oauth-protected-resource'));
        $this->assertSame(405, $res->status);
    }

    public function test_mcp_requires_a_bearer_token() {
        $router = new Hm_MCP_Router($this->config());
        $res = $router->handle($this->request('POST', '/mcp', ['Content-Type' => 'application/json'], '{}'));
        $this->assertSame(401, $res->status);
        $challenge = $res->header('WWW-Authenticate');
        $this->assertStringStartsWith('Bearer ', $challenge);
        $this->assertStringContainsString('resource_metadata="https://mail.example.com/.well-known/oauth-protected-resource/mcp"', $challenge);
        $this->assertStringNotContainsString('error=', $challenge);
        $this->assertStringNotContainsString('scope=', $challenge);
    }

    public function test_mcp_rejects_an_unknown_token() {
        $config = $this->config();
        setup_db($config);
        $router = new Hm_MCP_Router($config);
        $res = $router->handle($this->request('POST', '/mcp', ['Authorization' => 'Bearer cyp_at_unknown_token_value_0123456789']));
        $this->assertSame(401, $res->status);
        $this->assertStringContainsString('error="invalid_token"', $res->header('WWW-Authenticate'));
    }

    public function test_mcp_without_storage_is_unavailable() {
        $router = new Hm_MCP_Router($this->config());
        $res = $router->handle($this->request('POST', '/mcp', ['Authorization' => 'Bearer cyp_at_unknown_token_value_0123456789']));
        $this->assertSame(503, $res->status);
    }

    public function test_preflight_and_not_found() {
        $router = new Hm_MCP_Router($this->config());
        $res = $router->handle($this->request('OPTIONS', '/mcp'));
        $this->assertSame(204, $res->status);
        $this->assertStringContainsString('Authorization', $res->header('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('Mcp-Session-Id', $res->header('Access-Control-Expose-Headers'));
        $res = $router->handle($this->request('GET', '/oauth/unknown-endpoint'));
        $this->assertSame(404, $res->status);
    }

    public function test_bearer_token_parsing() {
        $req = $this->request('GET', '/mcp', ['Authorization' => 'Bearer abc.DEF-123_~+/=']);
        $this->assertSame('abc.DEF-123_~+/=', $req->bearer_token());
        $req = $this->request('GET', '/mcp', ['Authorization' => 'Basic abc']);
        $this->assertFalse($req->bearer_token());
        $req = $this->request('GET', '/mcp', ['Authorization' => 'Bearer a b']);
        $this->assertFalse($req->bearer_token());
    }
}
