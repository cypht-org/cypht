<?php

/**
 * Loads the libraries of the mcp module set
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH.'modules/mcp/hm-mcp-config.php';
require_once APP_PATH.'modules/mcp/hm-mcp-crypto.php';
require_once APP_PATH.'modules/mcp/hm-mcp-errors.php';
require_once APP_PATH.'modules/mcp/hm-mcp-http.php';
require_once APP_PATH.'modules/mcp/hm-mcp-permissions.php';
require_once APP_PATH.'modules/mcp/hm-mcp-store.php';
require_once APP_PATH.'modules/mcp/hm-mcp-auth.php';
require_once APP_PATH.'modules/mcp/hm-mcp-format.php';
require_once APP_PATH.'modules/mcp/hm-mcp-context.php';
require_once APP_PATH.'modules/mcp/hm-mcp-catalog.php';
require_once APP_PATH.'modules/mcp/hm-mcp-mail.php';
require_once APP_PATH.'modules/mcp/hm-mcp-executor.php';
require_once APP_PATH.'modules/mcp/hm-mcp-files.php';
require_once APP_PATH.'modules/mcp/hm-mcp-endpoint.php';
require_once APP_PATH.'modules/mcp/hm-mcp-rest.php';
require_once APP_PATH.'modules/mcp/hm-mcp-services.php';
require_once APP_PATH.'modules/mcp/hm-mcp-router.php';
