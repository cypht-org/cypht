<?php

/**
 * Sieve Sync Configuration Helper
 * 
 * This file provides functions to load Sieve configuration for users
 * from various sources (user config, database, etc.)
 * 
 * @package Cypht
 * @subpackage scripts
 */

if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', true);
    define('APP_PATH', dirname(dirname(__FILE__)) . '/');
}

/**
 * Get Sieve configuration for a user/server
 * 
 * @param string $user_id User identifier
 * @param string $imap_server_id IMAP server identifier
 * @return array|null Sieve configuration or null if not found
 */
function get_user_sieve_config($user_id, $imap_server_id) {
    // Try to load from Hm_IMAP_List (runtime cache)
    if (class_exists('Hm_IMAP_List')) {
        $imap_account = Hm_IMAP_List::dump($imap_server_id, true);
        if ($imap_account && isset($imap_account['sieve_config_host'])) {
            return parse_sieve_config($imap_account);
        }
    }
    
    // Try to load from user config file
    $user_config = load_user_config_from_file($user_id);
    if ($user_config) {
        $imap_servers = isset($user_config['imap_servers']) ? $user_config['imap_servers'] : array();
        if (isset($imap_servers[$imap_server_id])) {
            $server = $imap_servers[$imap_server_id];
            if (isset($server['sieve_config_host'])) {
                return parse_sieve_config($server);
            }
        }
    }
    
    // Try environment variables (for testing)
    if (getenv('SIEVE_HOST') && getenv('SIEVE_USER') && getenv('SIEVE_PASS')) {
        return array(
            'provider' => detect_provider(getenv('SIEVE_HOST')),
            'host' => getenv('SIEVE_HOST'),
            'port' => getenv('SIEVE_PORT') ?: 4190,
            'username' => getenv('SIEVE_USER'),
            'password' => getenv('SIEVE_PASS')
        );
    }
    
    return null;
}

/**
 * Parse Sieve configuration from IMAP server config
 * 
 * @param array $server_config IMAP server configuration
 * @return array Sieve configuration
 */
function parse_sieve_config($server_config) {
    // Parse host:port format
    $sieve_host = $server_config['sieve_config_host'];
    $host = $sieve_host;
    $port = 4190;
    
    if (strpos($sieve_host, ':') !== false) {
        list($host, $port) = explode(':', $sieve_host, 2);
        $port = (int)$port;
    }
    
    // Detect provider
    $provider = isset($server_config['sieve_provider']) ? 
        $server_config['sieve_provider'] : 
        detect_provider($host);
    
    // Get credentials
    $username = isset($server_config['user']) ? $server_config['user'] : '';
    $password = isset($server_config['pass']) ? $server_config['pass'] : '';
    
    return array(
        'provider' => $provider,
        'host' => $host,
        'port' => $port,
        'username' => $username,
        'password' => $password
    );
}

/**
 * Detect Sieve provider from hostname
 * 
 * @param string $host Hostname
 * @return string Provider identifier
 */
function detect_provider($host) {
    if (strpos($host, 'migadu.com') !== false) {
        return 'migadu';
    }
    return 'generic';
}

/**
 * Load user config from file
 * 
 * @param string $user_id User identifier
 * @return array|null User configuration or null
 */
function load_user_config_from_file($user_id) {
    $config_file = APP_PATH . 'users/' . $user_id . '.txt';
    
    if (!file_exists($config_file)) {
        return null;
    }
    
    $content = file_get_contents($config_file);
    if (!$content) {
        return null;
    }
    
    // Try to decode (might be encrypted)
    $data = @json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $data;
    }
    
    return null;
}

/**
 * Get Sieve config from database
 * 
 * @param string $user_id User identifier
 * @param string $imap_server_id IMAP server identifier
 * @return array|null Sieve configuration or null
 */
function get_sieve_config_from_db($user_id, $imap_server_id) {
    // This would query the database for user's IMAP server configuration
    // Implementation depends on your database setup
    
    // Example:
    // $db = get_db_connection();
    // $stmt = $db->prepare("SELECT * FROM imap_servers WHERE user_id = ? AND id = ?");
    // $stmt->execute([$user_id, $imap_server_id]);
    // $server = $stmt->fetch();
    // 
    // if ($server && $server['sieve_host']) {
    //     return parse_sieve_config($server);
    // }
    
    return null;
}

