<?php

/**
 * Request router for the MCP server, OAuth endpoints and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Routes requests for the paths owned by the mcp module set
 * @subpackage mcp/lib
 */
class Hm_MCP_Router {

    /* Hm_MCP_Config */
    public $config;

    /* site configuration */
    public $site_config;

    /**
     * @param object $site_config site configuration
     */
    public function __construct($site_config) {
        $this->site_config = $site_config;
        $this->config = new Hm_MCP_Config($site_config);
    }

    /**
     * Handle one request
     * @param Hm_MCP_Http_Request $request request details
     * @return Hm_MCP_Http_Response
     */
    public function handle($request) {
        if (!$this->config->is_configured()) {
            return Hm_MCP_Http_Response::error(503, 'not_configured',
                'The MCP server is not configured. Set MCP_PUBLIC_URL to the public HTTPS URL of this Cypht installation.');
        }
        if (!$this->config->host_allowed($request->host())) {
            return Hm_MCP_Http_Response::error(403, 'invalid_host', 'The Host header is not allowed for this server.');
        }
        $path = rtrim($request->path, '/');
        if ($path === '') {
            $path = '/';
        }
        $machine = $path !== '/oauth/authorize';
        if ($machine && $request->method === 'OPTIONS') {
            return $this->cors(new Hm_MCP_Http_Response(204));
        }
        $response = $this->route($path, $request);
        return $machine ? $this->cors($response) : $response;
    }

    /**
     * @param string $path normalized path
     * @param Hm_MCP_Http_Request $request request details
     * @return Hm_MCP_Http_Response
     */
    protected function route($path, $request) {
        switch ($path) {
            case '/.well-known/oauth-protected-resource':
            case '/.well-known/oauth-protected-resource/mcp':
                return $this->only_get($request, function () { return $this->protected_resource_metadata(); });
            case '/mcp':
                return $this->mcp($request);
        }
        return Hm_MCP_Http_Response::error(404, 'not_found', 'Not found');
    }

    /**
     * Restrict an endpoint to GET and HEAD
     * @param Hm_MCP_Http_Request $request request details
     * @param callable $handler endpoint handler
     * @return Hm_MCP_Http_Response
     */
    protected function only_get($request, $handler) {
        if (!in_array($request->method, ['GET', 'HEAD'], true)) {
            return Hm_MCP_Http_Response::error(405, 'method_not_allowed', 'Method not allowed', ['Allow' => 'GET, HEAD, OPTIONS']);
        }
        return $handler();
    }

    /**
     * Protected resource metadata (RFC 9728)
     * @return Hm_MCP_Http_Response
     */
    public function protected_resource_metadata() {
        return Hm_MCP_Http_Response::json([
            'resource' => $this->config->resource(),
            'authorization_servers' => [$this->config->issuer()],
            'scopes_supported' => Hm_MCP_Permissions::scopes(),
            'bearer_methods_supported' => ['header'],
            'resource_name' => $this->site_config->get('app_name', 'Cypht'),
        ], 200, ['Cache-Control' => 'public, max-age=300']);
    }

    /**
     * Bearer challenge for unauthenticated requests (RFC 6750, RFC 9728)
     * @param string|false $error OAuth error code when a token was rejected
     * @param string $description error description
     * @return string WWW-Authenticate header value
     */
    public function challenge($error = false, $description = '') {
        $parts = [];
        if ($error) {
            $parts[] = sprintf('error="%s"', $error);
            if ($description !== '') {
                $parts[] = sprintf('error_description="%s"', str_replace(['"', '\\'], '', $description));
            }
        }
        $parts[] = sprintf('resource_metadata="%s"', $this->config->resource_metadata_url());
        $parts[] = sprintf('scope="%s"', Hm_MCP_Permissions::scope('read'));
        return 'Bearer '.implode(', ', $parts);
    }

    /**
     * Unauthorized response with the bearer challenge
     * @param string|false $error OAuth error code
     * @param string $description error description
     * @return Hm_MCP_Http_Response
     */
    public function unauthorized($error = false, $description = '') {
        $message = $description !== '' ? $description : 'A valid bearer token is required.';
        return Hm_MCP_Http_Response::error(401, $error ?: 'unauthorized', $message,
            ['WWW-Authenticate' => $this->challenge($error, $description)]);
    }

    /**
     * MCP Streamable HTTP endpoint
     * @param Hm_MCP_Http_Request $request request details
     * @return Hm_MCP_Http_Response
     */
    protected function mcp($request) {
        $token = $request->bearer_token();
        if ($token === false) {
            return $this->unauthorized();
        }
        return $this->unauthorized('invalid_token', 'The access token is invalid or expired.');
    }

    /**
     * Add CORS headers to machine endpoints. These endpoints never use cookies,
     * so allowing any origin does not expose ambient credentials.
     * @param Hm_MCP_Http_Response $response response
     * @return Hm_MCP_Http_Response
     */
    protected function cors($response) {
        $response->with_header('Access-Control-Allow-Origin', '*');
        $response->with_header('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
        $response->with_header('Access-Control-Allow-Headers',
            'Authorization, Content-Type, Accept, Mcp-Session-Id, Mcp-Protocol-Version, Mcp-Method, Mcp-Name, Last-Event-ID');
        $response->with_header('Access-Control-Expose-Headers', 'Mcp-Session-Id, Mcp-Protocol-Version, WWW-Authenticate');
        $response->with_header('Access-Control-Max-Age', '600');
        return $response;
    }
}
