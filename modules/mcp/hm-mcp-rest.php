<?php

/**
 * REST API built from the operation catalog
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Serves /api/v1. Every catalog operation with a REST route has a resource style
 * endpoint, and every operation can also be called with POST /api/v1/tools/{name}.
 * @subpackage mcp/lib
 */
class Hm_MCP_Rest {

    /* token kinds accepted by the REST API */
    const TOKEN_KINDS = ['personal'];

    private $services;

    /**
     * @param Hm_MCP_Services $services services
     */
    public function __construct($services) {
        $this->services = $services;
    }

    /**
     * Handle a request below /api/v1
     * @param Hm_MCP_Http_Request $request request
     * @param string $path path below /api/v1
     * @return Hm_MCP_Http_Response
     */
    public function handle($request, $path) {
        $path = '/'.trim((string) $path, '/');
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        if ($path === '/' || $path === '/openapi.json') {
            if ($method !== 'GET') {
                return Hm_MCP_Http_Response::error(405, 'method_not_allowed', 'Method not allowed', ['Allow' => 'GET, HEAD, OPTIONS']);
            }
            return $path === '/' ? $this->index() : Hm_MCP_Http_Response::json($this->openapi(), 200, ['Cache-Control' => 'public, max-age=300']);
        }
        if (preg_match('#^/files/([^/]+)$#', $path, $matches)) {
            /* capability links authenticate with the token in the URL */
            return $this->services->files()->download($request, $matches[1]);
        }
        if ($path === '/scheduled/run') {
            /* runner tokens only work here */
            return $this->services->scheduler()->run($request);
        }
        list($name, $params, $allowed) = $this->match($method, $path);
        if ($name === false) {
            if ($allowed) {
                return Hm_MCP_Http_Response::error(405, 'method_not_allowed', 'Method not allowed',
                    ['Allow' => implode(', ', array_unique($allowed)).', OPTIONS']);
            }
            return Hm_MCP_Http_Response::error(404, 'not_found', 'Not found');
        }
        $token = $request->bearer_token();
        if ($token === false) {
            return $this->unauthorized('A personal access token is required. Create one in Cypht under Settings, API and MCP.');
        }
        $auth = $this->services->auth()->authenticate($token, self::TOKEN_KINDS);
        if (!$auth['ok']) {
            if ($auth['status'] === 401) {
                return $this->unauthorized($auth['message'], 'invalid_token');
            }
            return Hm_MCP_Http_Response::error($auth['status'], $auth['error'], $auth['message']);
        }
        $op = $this->services->catalog()->get($name);
        $args = $this->arguments($request, $method, $op, $params);
        if ($args instanceof Hm_MCP_Http_Response) {
            return $args;
        }
        $res = $this->services->executor($auth['principal'], 'rest')->run($name, $args);
        if ($res['ok']) {
            return Hm_MCP_Http_Response::json(self::public_result($res['result']));
        }
        $error = $res['error'];
        $headers = [];
        if ($error->error_code === 'reauth_required') {
            $headers['WWW-Authenticate'] = 'Bearer realm="cypht", error="invalid_token"';
        }
        return Hm_MCP_Http_Response::json(['error' => $error->to_array()], $error->http_status(), $headers);
    }

    /**
     * Remove internal top level keys (starting with "_") from a result
     * @param array $result operation result
     * @return array
     */
    public static function public_result($result) {
        foreach (array_keys($result) as $key) {
            if (is_string($key) && $key !== '' && $key[0] === '_') {
                unset($result[$key]);
            }
        }
        return $result;
    }

    /**
     * @param string $message description
     * @param string|false $error OAuth error code
     * @return Hm_MCP_Http_Response
     */
    private function unauthorized($message, $error = false) {
        $challenge = 'Bearer realm="cypht"'.($error ? ', error="'.$error.'"' : '');
        return Hm_MCP_Http_Response::error(401, $error ?: 'unauthorized', $message, ['WWW-Authenticate' => $challenge]);
    }

    /**
     * Routes: [method, regex, operation name, placeholder names, placeholder count]
     * @return array
     */
    private function routes() {
        $routes = [];
        foreach ($this->services->catalog()->all() as $name => $op) {
            if (empty($op['rest']) || !Hm_MCP_Catalog::exposed($op)) {
                continue;
            }
            list($method, $template) = $op['rest'];
            preg_match_all('/\{([a-z_]+)\}/', $template, $names);
            $regex = '#^'.preg_replace('/\\\\\{[a-z_]+\\\\\}/', '([^/]+)', preg_quote($template, '#')).'$#';
            $routes[] = [$method, $regex, $name, $names[1]];
        }
        $routes[] = ['POST', '#^/tools/([a-z_]+)$#', null, ['name']];
        usort($routes, function ($a, $b) { return count($a[3]) <=> count($b[3]); });
        return $routes;
    }

    /**
     * Find the operation for a request
     * @param string $method HTTP method
     * @param string $path path below /api/v1
     * @return array [operation name or false, path parameters, methods allowed for the path]
     */
    private function match($method, $path) {
        $allowed = [];
        foreach ($this->routes() as list($route_method, $regex, $name, $names)) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            $params = [];
            foreach ($names as $index => $param) {
                $params[$param] = rawurldecode($matches[$index + 1]);
            }
            if ($name === null) {
                $name = $params['name'];
                unset($params['name']);
                $op = $this->services->catalog()->get($name);
                if (!$op || !Hm_MCP_Catalog::exposed($op)) {
                    continue;
                }
            }
            if ($route_method !== $method) {
                $allowed[] = $route_method === 'GET' ? 'GET, HEAD' : $route_method;
                continue;
            }
            return [$name, $params, []];
        }
        return [false, [], $allowed];
    }

    /**
     * Build the operation arguments from the path, query string or JSON body
     * @return array|Hm_MCP_Http_Response arguments or an error response
     */
    private function arguments($request, $method, $op, $params) {
        if ($method === 'GET') {
            $args = self::coerce($op['input'], $request->query);
        } else {
            $args = $request->json();
            if ($args === null || (is_array($args) && $args !== [] && array_is_list($args))) {
                return Hm_MCP_Http_Response::error(400, 'invalid_argument', 'The request body must be a JSON object.');
            }
        }
        foreach ($params as $name => $value) {
            $args[$name] = $value;
        }
        return $args;
    }

    /**
     * Convert query string values to the types of the input schema
     * @param array $schema input schema
     * @param array $query query values
     * @return array
     */
    public static function coerce($schema, $query) {
        $args = [];
        foreach ($query as $name => $value) {
            $type = $schema['properties'][$name]['type'] ?? 'string';
            if ($type === 'integer' && is_string($value) && preg_match('/^-?\d+$/', $value)) {
                $value = (int) $value;
            } elseif ($type === 'boolean' && is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', '0', 'false', 'no'], true)) {
                $value = in_array(strtolower($value), ['1', 'true', 'yes'], true);
            } elseif ($type === 'array' && is_string($value)) {
                $value = array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
            }
            $args[$name] = $value;
        }
        return $args;
    }

    /**
     * @return Hm_MCP_Http_Response API index
     */
    private function index() {
        $config = $this->services->config;
        return Hm_MCP_Http_Response::json([
            'name' => 'Cypht REST API',
            'version' => 'v1',
            'openapi' => $config->api_url().'/openapi.json',
            'mcp' => $config->resource(),
            'authentication' => 'Authorization: Bearer <personal access token from Settings, API and MCP>',
        ], 200, ['Cache-Control' => 'public, max-age=300']);
    }

    /**
     * OpenAPI 3.1 description of the REST API
     * @return array
     */
    public function openapi() {
        $config = $this->services->config;
        $error = Hm_MCP_Catalog::normalize(Hm_MCP_Catalog::obj(['error' => Hm_MCP_Catalog::obj([
            'code' => ['type' => 'string'],
            'message' => ['type' => 'string'],
            'details' => ['type' => 'object'],
        ], ['code', 'message'])], ['error']));
        $paths = [];
        foreach ($this->services->catalog()->all() as $name => $op) {
            if (!Hm_MCP_Catalog::exposed($op)) {
                continue;
            }
            $operation = [
                'operationId' => $name,
                'summary' => $op['title'],
                'description' => $op['description'],
                'x-cypht-permission' => $op['permission'],
                'responses' => [
                    '200' => ['description' => 'Result', 'content' => ['application/json' => ['schema' => Hm_MCP_Catalog::normalize($op['output'])]]],
                    'default' => ['description' => 'Error', 'content' => ['application/json' => ['schema' => $error]]],
                ],
            ];
            if (!empty($op['rest'])) {
                list($method, $template) = $op['rest'];
                $paths[$template][strtolower($method)] = $this->openapi_operation($operation, $op['input'], $method, $template);
            }
            $paths['/tools/'.$name]['post'] = $this->openapi_operation(array_merge($operation, [
                'operationId' => 'tool_'.$name,
                'summary' => $op['title'].' (tool call)',
            ]), $op['input'], 'POST', '/tools/'.$name);
        }
        $paths['/scheduled/run']['post'] = [
            'operationId' => 'run_scheduled',
            'summary' => 'Send due scheduled messages',
            'description' => 'Sends the scheduled messages of the user that are due. Call it every minute from a scheduled task, with a runner token created in Cypht under Settings, API and MCP.',
            'security' => [['runnerAuth' => []]],
            'responses' => [
                '200' => ['description' => 'Run report', 'content' => ['application/json' => ['schema' => Hm_MCP_Catalog::normalize(Hm_MCP_Catalog::obj([
                    'sent' => ['type' => 'integer'],
                    'failed' => ['type' => 'integer'],
                    'waiting' => ['type' => 'integer', 'description' => 'Scheduled messages that are not due yet'],
                    'failures' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'errors' => ['type' => 'array', 'items' => ['type' => 'object']],
                ], ['sent', 'failed', 'waiting']))]]],
                'default' => ['description' => 'Error', 'content' => ['application/json' => ['schema' => $error]]],
            ],
        ];
        $paths['/files/{token}']['get'] = [
            'operationId' => 'download_attachment',
            'summary' => 'Download an attachment',
            'description' => 'Temporary link returned by get_attachment. The token in the URL is the credential: no Authorization header is needed.',
            'security' => [],
            'parameters' => [['name' => 'token', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
            'responses' => [
                '200' => ['description' => 'File content', 'content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]]],
                '404' => ['description' => 'The link is not valid or expired'],
            ],
        ];
        ksort($paths);
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Cypht REST API',
                'version' => '1.0.0',
                'description' => 'Read, search, organize, draft and send email in Cypht. Operations not allowed by the permissions of the token return 403.',
            ],
            'servers' => [['url' => $config->api_url()]],
            'components' => ['securitySchemes' => [
                'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer',
                    'description' => 'Personal access token created in Cypht under Settings, API and MCP'],
                'runnerAuth' => ['type' => 'http', 'scheme' => 'bearer',
                    'description' => 'Runner token created in Cypht under Settings, API and MCP, only for /scheduled/run'],
            ]],
            'security' => [['bearerAuth' => []]],
            'paths' => $paths,
        ];
    }

    /**
     * @return array OpenAPI operation with parameters or request body
     */
    private function openapi_operation($operation, $input, $method, $template) {
        preg_match_all('/\{([a-z_]+)\}/', $template, $names);
        $parameters = [];
        foreach ($names[1] as $param) {
            $parameters[] = ['name' => $param, 'in' => 'path', 'required' => true,
                'schema' => $input['properties'][$param] ?? ['type' => 'string']];
        }
        $rest = $input;
        foreach ($names[1] as $param) {
            unset($rest['properties'][$param]);
            if (isset($rest['required'])) {
                $rest['required'] = array_values(array_diff($rest['required'], [$param]));
                if (!$rest['required']) {
                    unset($rest['required']);
                }
            }
        }
        if ($method === 'GET') {
            foreach ($rest['properties'] ?? [] as $param => $schema) {
                $parameters[] = ['name' => $param, 'in' => 'query', 'required' => in_array($param, $rest['required'] ?? [], true),
                    'description' => $schema['description'] ?? '', 'schema' => $schema];
            }
        } elseif (!empty($rest['properties'])) {
            $operation['requestBody'] = ['required' => !empty($rest['required']),
                'content' => ['application/json' => ['schema' => Hm_MCP_Catalog::normalize($rest)]]];
        }
        if ($parameters) {
            $operation['parameters'] = $parameters;
        }
        return $operation;
    }
}
