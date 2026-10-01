<?php

/**
 * MCP server and REST API module set
 *
 * Exposes mail reading, organizing, drafting and sending to MCP clients (such as
 * ChatGPT) and to a REST API. Disabled unless "mcp" is listed in the enabled
 * modules; each user then turns it on and sets permissions under Settings.
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

handler_source('mcp');
output_source('mcp');

return array(
    /* URL paths served by routes.php instead of the page dispatcher */
    'allowed_routes' => array(
        '/mcp' => 'mcp',
        '/.well-known/oauth-protected-resource*' => 'mcp',
        '/.well-known/oauth-authorization-server*' => 'mcp',
        '/oauth/*' => 'mcp',
        '/api/v1' => 'mcp',
        '/api/v1/*' => 'mcp',
    ),
);
