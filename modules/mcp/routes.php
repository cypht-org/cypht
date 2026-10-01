<?php

/**
 * Path route handler for the mcp module set
 *
 * Loaded by Hm_Path_Router for the paths declared in setup.php. It serves the
 * MCP endpoint, the OAuth 2.1 authorization server and the REST API.
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/mcp/hm-mcp.php';

return function ($config, $path) {
    /* one request may talk to several mail servers */
    @set_time_limit(120);
    $router = new Hm_MCP_Router($config);
    $router->handle(Hm_MCP_Http_Request::from_globals($path))->send();
};
