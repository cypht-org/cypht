<?php

/**
 * Settings for the "mcp" module set (MCP server and REST API).
 * The module set is disabled unless "mcp" is added to CYPHT_MODULES.
 */
return [
    /*
    | Public URL of this Cypht installation, as reached by MCP and API clients.
    | Required to enable the endpoints. Example: https://mail.example.com
    */
    'mcp_public_url' => env('MCP_PUBLIC_URL', ''),

    /*
    | Extra host names allowed in the Host header, comma separated. The host of
    | mcp_public_url and the loopback names are always allowed.
    */
    'mcp_allowed_hosts' => env('MCP_ALLOWED_HOSTS', ''),

    /* OAuth token lifetimes in seconds */
    'mcp_access_token_ttl' => env('MCP_ACCESS_TOKEN_TTL', 3600),
    'mcp_refresh_token_ttl' => env('MCP_REFRESH_TOKEN_TTL', 2592000),

    /* Lifetime in seconds of temporary attachment download links */
    'mcp_file_link_ttl' => env('MCP_FILE_LINK_TTL', 900),

    /* Days to keep entries in the activity log */
    'mcp_activity_retention_days' => env('MCP_ACTIVITY_RETENTION_DAYS', 90),

    /*
    | Hosts allowed to publish OAuth Client ID Metadata Documents (CIMD),
    | comma separated. ChatGPT uses https://chatgpt.com/oauth/.../client.json
    */
    'mcp_cimd_allowed_hosts' => env('MCP_CIMD_ALLOWED_HOSTS', 'chatgpt.com'),

    /*
    | Hosts allowed as download sources for files attached by MCP clients, comma
    | separated. A leading "*." matches subdomains. The defaults cover ChatGPT.
    */
    'mcp_upload_allowed_hosts' => env('MCP_UPLOAD_ALLOWED_HOSTS', 'files.oaiusercontent.com,*.oaiusercontent.com,files.openaiusercontent.com,*.blob.core.windows.net'),

    /* Maximum size in bytes of one attachment added through MCP or the API */
    'mcp_max_upload_bytes' => env('MCP_MAX_UPLOAD_BYTES', 26214400),

    /*
    | Request header holding the client IP when Cypht runs behind a trusted
    | reverse proxy, for example X-Forwarded-For or CF-Connecting-IP. Leave
    | empty to use the connection address.
    */
    'mcp_client_ip_header' => env('MCP_CLIENT_IP_HEADER', ''),
];
