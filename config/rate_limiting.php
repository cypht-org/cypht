<?php

/**
 * Rate limiting configuration
 * 
 * This file contains the default rate limiting settings for Cypht.
 * Administrators can modify these settings to customize rate limiting behavior.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Set to true to enable rate limiting for API endpoints.
    | Default: true
    |
    */
    'enabled' => env('RATE_LIMITING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Rate Limits
    |--------------------------------------------------------------------------
    |
    | Default rate limiting configuration for all endpoints.
    |
    */
    'defaults' => [
        'window_size' => env('RATE_LIMIT_WINDOW_SIZE', 3600), // 1 hour in seconds
        'max_requests' => env('RATE_LIMIT_MAX_REQUESTS', 100), // 100 requests per hour
        'burst_limit' => env('RATE_LIMIT_BURST_LIMIT', 10), // 10 requests per burst window
        'burst_window' => env('RATE_LIMIT_BURST_WINDOW', 60), // 1 minute burst window
        'penalty_duration' => env('RATE_LIMIT_PENALTY_DURATION', 3600), // 1 hour penalty
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpoint-Specific Rate Limits
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for specific endpoints.
    | These settings override the default limits.
    |
    */
    'endpoints' => [
        'ajax_imap_report_spam' => [
            'window_size' => env('SPAM_REPORT_WINDOW_SIZE', 3600), // 1 hour
            'max_requests' => env('SPAM_REPORT_MAX_REQUESTS', 50), // 50 spam reports per hour
            'burst_limit' => env('SPAM_REPORT_BURST_LIMIT', 5), // 5 reports per minute
            'burst_window' => env('SPAM_REPORT_BURST_WINDOW', 60), // 1 minute
            'penalty_duration' => env('SPAM_REPORT_PENALTY_DURATION', 7200), // 2 hour penalty
        ],
        
        'ajax_imap_message_action' => [
            'window_size' => env('MESSAGE_ACTION_WINDOW_SIZE', 3600),
            'max_requests' => env('MESSAGE_ACTION_MAX_REQUESTS', 200),
            'burst_limit' => env('MESSAGE_ACTION_BURST_LIMIT', 20),
            'burst_window' => env('MESSAGE_ACTION_BURST_WINDOW', 60),
        ],
        
        'ajax_imap_search' => [
            'window_size' => env('SEARCH_WINDOW_SIZE', 3600),
            'max_requests' => env('SEARCH_MAX_REQUESTS', 300),
            'burst_limit' => env('SEARCH_BURST_LIMIT', 30),
            'burst_window' => env('SEARCH_BURST_WINDOW', 60),
        ],
        
        'ajax_imap_message_list' => [
            'window_size' => env('MESSAGE_LIST_WINDOW_SIZE', 3600),
            'max_requests' => env('MESSAGE_LIST_MAX_REQUESTS', 500),
            'burst_limit' => env('MESSAGE_LIST_BURST_LIMIT', 50),
            'burst_window' => env('MESSAGE_LIST_BURST_WINDOW', 60),
        ],
        
        'ajax_imap_folder_display' => [
            'window_size' => env('FOLDER_DISPLAY_WINDOW_SIZE', 3600),
            'max_requests' => env('FOLDER_DISPLAY_MAX_REQUESTS', 200),
            'burst_limit' => env('FOLDER_DISPLAY_BURST_LIMIT', 20),
            'burst_window' => env('FOLDER_DISPLAY_BURST_WINDOW', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Storage
    |--------------------------------------------------------------------------
    |
    | Configure the storage backend for rate limiting data.
    | Options: 'cache', 'redis', 'memcached'
    |
    */
    'storage' => env('RATE_LIMITING_STORAGE', 'cache'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Headers
    |--------------------------------------------------------------------------
    |
    | Enable/disable rate limiting headers in responses.
    | Headers include: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset
    |
    */
    'headers' => env('RATE_LIMITING_HEADERS', true),

    /*
    |--------------------------------------------------------------------------
    | Exempt IP Addresses
    |--------------------------------------------------------------------------
    |
    | IP addresses that are exempt from rate limiting.
    | Useful for monitoring, admin access, or trusted services.
    |
    */
    'exempt_ips' => [
        // Add exempt IP addresses here
        // '127.0.0.1',
        // '::1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exempt User Agents
    |--------------------------------------------------------------------------
    |
    | User agents that are exempt from rate limiting.
    | Useful for monitoring tools or trusted applications.
    |
    */
    'exempt_user_agents' => [
        // Add exempt user agents here
        // 'MonitoringBot/1.0',
        // 'HealthCheck/1.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging of rate limit violations.
    |
    */
    'logging' => [
        'enabled' => env('RATE_LIMITING_LOGGING', true),
        'level' => env('RATE_LIMITING_LOG_LEVEL', 'warning'), // debug, info, warning, error
    ],
]; 