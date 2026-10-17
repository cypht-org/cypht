<?php

/**
 * HTTP helpers for the MCP and REST API routes
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Request details for a routed API request
 * @subpackage mcp/lib
 */
class Hm_MCP_Http_Request {

    /* HTTP method in upper case */
    public $method = 'GET';

    /* path relative to the Cypht installation, without query string */
    public $path = '/';

    /* query string values */
    public $query = [];

    /* lower case header name => value */
    public $headers = [];

    /* raw server values */
    public $server = [];

    /* form values for urlencoded POST requests */
    public $form = [];

    /* raw request body */
    private $body = null;

    /* callable that returns the raw body */
    private $body_reader;

    /**
     * @param string $method HTTP method
     * @param string $path relative path
     * @param array $query query values
     * @param array $headers header values keyed by name
     * @param string|callable|null $body raw body or a reader callable
     * @param array $server server values
     * @param array $form parsed form values
     */
    public function __construct($method, $path, $query = [], $headers = [], $body = null, $server = [], $form = []) {
        $this->method = strtoupper((string) $method);
        $this->path = $path;
        $this->query = is_array($query) ? $query : [];
        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = (string) $value;
        }
        if (is_callable($body)) {
            $this->body_reader = $body;
        } else {
            $this->body = $body === null ? '' : (string) $body;
        }
        $this->server = $server;
        $this->form = is_array($form) ? $form : [];
    }

    /**
     * Build a request from the PHP superglobals
     * @param string $path relative request path
     * @return Hm_MCP_Http_Request
     */
    public static function from_globals($path) {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headers[str_replace('_', '-', substr($name, 5))] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
        if (!isset($headers['AUTHORIZATION']) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        $reader = function () {
            return (string) file_get_contents('php://input', false, null, 0, Hm_MCP_Http_Request::MAX_BODY_BYTES + 1);
        };
        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $_GET, $headers, $reader, $_SERVER, $_POST);
    }

    /* largest request body read, in bytes */
    const MAX_BODY_BYTES = 33554432;

    /**
     * @param string $name header name
     * @param string $default value when missing
     * @return string
     */
    public function header($name, $default = '') {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Raw request body
     * @return string
     */
    public function body() {
        if ($this->body === null) {
            $this->body = ($this->body_reader)();
        }
        return $this->body;
    }

    /**
     * Decode a JSON request body
     * @return array|null null if the body is not a JSON object
     */
    public function json() {
        $body = $this->body();
        if (trim($body) === '') {
            return [];
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Form values from a urlencoded body
     * @return array
     */
    public function form() {
        if (!empty($this->form)) {
            return $this->form;
        }
        $type = strtolower($this->header('content-type'));
        if (strpos($type, 'application/x-www-form-urlencoded') === 0) {
            $data = [];
            parse_str($this->body(), $data);
            return $data;
        }
        return [];
    }

    /**
     * Bearer token from the Authorization header
     * @return string|false
     */
    public function bearer_token() {
        $value = $this->header('authorization');
        if (preg_match('/^Bearer\s+([A-Za-z0-9._~+\/=-]+)\s*$/i', $value, $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * Host header without the port
     * @return string
     */
    public function host() {
        $host = strtolower(trim($this->header('host')));
        if ($host === '') {
            return '';
        }
        if ($host[0] === '[') {
            $end = strpos($host, ']');
            return $end === false ? $host : substr($host, 0, $end + 1);
        }
        return explode(':', $host, 2)[0];
    }

    /**
     * Client IP address
     * @param string $header optional trusted proxy header name
     * @return string
     */
    public function client_ip($header = '') {
        if ($header) {
            $value = $this->header($header);
            if ($value !== '') {
                $ip = trim(explode(',', $value)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        $ip = $this->server['REMOTE_ADDR'] ?? '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
}

/**
 * Response for a routed API request
 * @subpackage mcp/lib
 */
class Hm_MCP_Http_Response {

    public $status = 200;
    public $headers = [];
    public $body = '';

    /* optional callable that writes the body when sending */
    public $stream = null;

    /**
     * @param int $status HTTP status code
     * @param array $headers header name => value
     * @param string $body response body
     */
    public function __construct($status = 200, $headers = [], $body = '') {
        $this->status = (int) $status;
        $this->headers = $headers;
        $this->body = (string) $body;
    }

    /**
     * JSON response
     * @param mixed $data value to encode
     * @param int $status HTTP status code
     * @param array $headers extra headers
     * @return Hm_MCP_Http_Response
     */
    public static function json($data, $status = 200, $headers = []) {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return new self($status, array_merge([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
        ], $headers), $body === false ? '{}' : $body);
    }

    /**
     * Error response with a simple JSON body
     * @param int $status HTTP status code
     * @param string $code machine readable error code
     * @param string $message human readable description
     * @param array $headers extra headers
     * @return Hm_MCP_Http_Response
     */
    public static function error($status, $code, $message, $headers = []) {
        return self::json(['error' => ['code' => $code, 'message' => $message]], $status, $headers);
    }

    /**
     * OAuth style error response (RFC 6749 section 5.2)
     * @param int $status HTTP status code
     * @param string $error OAuth error code
     * @param string $description human readable description
     * @param array $headers extra headers
     * @return Hm_MCP_Http_Response
     */
    public static function oauth_error($status, $error, $description, $headers = []) {
        return self::json(['error' => $error, 'error_description' => $description], $status, $headers);
    }

    /**
     * HTML response with restrictive security headers
     * @param string $html page content
     * @param int $status HTTP status code
     * @return Hm_MCP_Http_Response
     */
    public static function html($html, $status = 200) {
        return new self($status, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'",
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ], $html);
    }

    /**
     * Redirect response
     * @param string $url destination
     * @param int $status redirect status code
     * @return Hm_MCP_Http_Response
     */
    public static function redirect($url, $status = 302) {
        return new self($status, ['Location' => $url, 'Cache-Control' => 'no-store']);
    }

    /**
     * Add or replace a header
     * @param string $name header name
     * @param string $value header value
     * @return Hm_MCP_Http_Response
     */
    public function with_header($name, $value) {
        foreach (array_keys($this->headers) as $existing) {
            if (strtolower($existing) === strtolower($name)) {
                unset($this->headers[$existing]);
            }
        }
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Read a header value
     * @param string $name header name
     * @return string|null
     */
    public function header($name) {
        foreach ($this->headers as $existing => $value) {
            if (strtolower($existing) === strtolower($name)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Decode a JSON body (used by tests)
     * @return mixed
     */
    public function decoded() {
        return json_decode($this->body, true);
    }

    /**
     * Send the response to the client
     * @return void
     */
    public function send() {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($this->status);
        header_remove('X-Powered-By');
        foreach ($this->headers as $name => $value) {
            header($name.': '.str_replace(["\r", "\n"], '', (string) $value));
        }
        if ($this->stream) {
            ($this->stream)();
            return;
        }
        echo $this->body;
    }
}
