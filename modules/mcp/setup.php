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

/* "API and MCP" settings page */
setup_base_page('mcp', 'core');
add_handler('mcp', 'mcp_settings_page', true, 'mcp', 'load_user_data', 'after');
add_output('mcp', 'mcp_settings_content', true, 'mcp', 'version_upgrade_checker', 'after');

/* settings menu link */
add_output('ajax_hm_folders', 'mcp_page_link', true, 'mcp', 'settings_menu_end', 'before');

return array(
    'allowed_pages' => array(
        'mcp',
    ),

    /* URL paths served by routes.php instead of the page dispatcher */
    'allowed_routes' => array(
        '/mcp' => 'mcp',
        '/.well-known/oauth-protected-resource*' => 'mcp',
        '/.well-known/oauth-authorization-server*' => 'mcp',
        /* answered with 404: clients that try OpenID Connect discovery first get a clear answer */
        '/.well-known/openid-configuration*' => 'mcp',
        '/oauth/*' => 'mcp',
        '/api/v1' => 'mcp',
        '/api/v1/*' => 'mcp',
    ),

    'allowed_get' => array(
        'mcp_activity' => FILTER_UNSAFE_RAW,
        'mcp_activity_page' => FILTER_VALIDATE_INT,
    ),

    'allowed_post' => array(
        'mcp_action' => FILTER_UNSAFE_RAW,
        'mcp_enabled' => FILTER_VALIDATE_BOOLEAN,
        'mcp_permissions' => array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_FORCE_ARRAY),
        'mcp_perm_mode' => FILTER_UNSAFE_RAW,
        'mcp_account_mode' => FILTER_UNSAFE_RAW,
        'mcp_account_ids' => array('filter' => FILTER_UNSAFE_RAW, 'flags' => FILTER_FORCE_ARRAY),
        'mcp_name' => FILTER_UNSAFE_RAW,
        'mcp_expires' => FILTER_VALIDATE_INT,
        'mcp_password' => FILTER_UNSAFE_RAW,
        'mcp_connection_id' => FILTER_UNSAFE_RAW,
    ),
);
