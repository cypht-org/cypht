<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Path_Router
 */
class Hm_Test_Path_Router extends TestCase {

    private $routes = [
        '/mcp' => 'mcp',
        '/oauth/*' => 'mcp',
        '/api/v1' => 'mcp',
        '/api/v1/*' => 'mcp',
        '/api/v1/special*' => 'other',
    ];

    public function test_relative_path_root_install() {
        $this->assertSame('/mcp', Hm_Path_Router::relative_path(['REQUEST_URI' => '/mcp', 'SCRIPT_NAME' => '/index.php']));
        $this->assertSame('/api/v1/messages', Hm_Path_Router::relative_path(['REQUEST_URI' => '/api/v1/messages?view=unread', 'SCRIPT_NAME' => '/index.php']));
        $this->assertSame('/', Hm_Path_Router::relative_path(['REQUEST_URI' => '/?page=home', 'SCRIPT_NAME' => '/index.php']));
    }

    public function test_relative_path_subdirectory_install() {
        $server = ['REQUEST_URI' => '/cypht/mcp', 'SCRIPT_NAME' => '/cypht/index.php'];
        $this->assertSame('/mcp', Hm_Path_Router::relative_path($server));
        $this->assertFalse(Hm_Path_Router::relative_path(['REQUEST_URI' => '/other/mcp', 'SCRIPT_NAME' => '/cypht/index.php']));
    }

    public function test_relative_path_rejects_unsafe_paths() {
        foreach (['/api/../mcp', '//mcp', '/api\\v1', "/mcp\0", 'mcp', ''] as $uri) {
            $this->assertFalse(Hm_Path_Router::relative_path(['REQUEST_URI' => $uri, 'SCRIPT_NAME' => '/index.php']), $uri);
        }
    }

    public function test_match_exact_and_prefix() {
        $this->assertSame('mcp', Hm_Path_Router::match($this->routes, '/mcp'));
        $this->assertSame('mcp', Hm_Path_Router::match($this->routes, '/mcp/'));
        $this->assertFalse(Hm_Path_Router::match($this->routes, '/mcpx'));
        $this->assertFalse(Hm_Path_Router::match($this->routes, '/mcp/extra'));
        $this->assertSame('mcp', Hm_Path_Router::match($this->routes, '/oauth/token'));
        $this->assertFalse(Hm_Path_Router::match($this->routes, '/oauth'));
        $this->assertSame('mcp', Hm_Path_Router::match($this->routes, '/api/v1'));
        $this->assertSame('mcp', Hm_Path_Router::match($this->routes, '/api/v1/messages'));
        $this->assertFalse(Hm_Path_Router::match($this->routes, '/'));
        $this->assertFalse(Hm_Path_Router::match('invalid', '/mcp'));
    }

    public function test_match_prefers_the_most_specific_pattern() {
        $this->assertSame('other', Hm_Path_Router::match($this->routes, '/api/v1/special/x'));
    }

    public function test_dispatch_ignores_disabled_or_unknown_module_sets() {
        $config = new Hm_Mock_Config();
        $config->mods = ['core'];
        $server = ['REQUEST_URI' => '/mcp', 'SCRIPT_NAME' => '/index.php'];
        $this->assertFalse(Hm_Path_Router::dispatch($config, ['allowed_routes' => $this->routes], $server));
        $this->assertFalse(Hm_Path_Router::dispatch($config, [], $server));
        $config->mods = ['core', 'mcp'];
        $this->assertFalse(Hm_Path_Router::dispatch($config, ['allowed_routes' => ['/mcp' => '../core']], $server));
        $this->assertFalse(Hm_Path_Router::dispatch($config, ['allowed_routes' => $this->routes],
            ['REQUEST_URI' => '/?page=home', 'SCRIPT_NAME' => '/index.php']));
    }

    public function test_merge_filters_merges_routes() {
        $existing = ['allowed_output' => [], 'allowed_get' => [], 'allowed_cookie' => [],
            'allowed_post' => [], 'allowed_server' => [], 'allowed_pages' => []];
        $merged = Hm_Module_Exec::merge_filters($existing, ['allowed_routes' => ['/a' => 'x']]);
        $merged = Hm_Module_Exec::merge_filters($merged, ['allowed_routes' => ['/b' => 'y', '/a' => 'z']]);
        $this->assertSame(['/a' => 'x', '/b' => 'y'], $merged['allowed_routes']);
    }
}
