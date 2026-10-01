<?php

/**
 * Loads the libraries of the mcp module set
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/mcp/hm-mcp-config.php';
require_once APP_PATH.'modules/mcp/hm-mcp-crypto.php';
require_once APP_PATH.'modules/mcp/hm-mcp-http.php';
require_once APP_PATH.'modules/mcp/hm-mcp-permissions.php';
require_once APP_PATH.'modules/mcp/hm-mcp-store.php';
require_once APP_PATH.'modules/mcp/hm-mcp-router.php';
