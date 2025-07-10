<?php
/**
 * Examine Session Data Script
 * 
 * This script examines the session data to understand what's causing
 * the server ID mismatch in spam reporting.
 */

// Define required constants
define('APP_PATH', __DIR__ . '/');
define('VENDOR_PATH', __DIR__ . '/vendor/');

// Include Cypht configuration
require_once 'lib/framework.php';

// Initialize configuration
$config = new Hm_Site_Config_File();

// Initialize session
$session = new Hm_User_Session($config);

if ($session->is_active()) {
    echo "✅ User session is active\n\n";
    
    // Get all session data
    $session_data = $session->dump();
    
    echo "=== Session Data Analysis ===\n\n";
    
    // Check for imap_auth_server_settings
    $auth_settings = $session->get('imap_auth_server_settings', array());
    if (!empty($auth_settings)) {
        echo "🔍 Found imap_auth_server_settings:\n";
        echo "   Server: " . (isset($auth_settings['server']) ? $auth_settings['server'] : 'Unknown') . "\n";
        echo "   Port: " . (isset($auth_settings['port']) ? $auth_settings['port'] : 'Unknown') . "\n";
        echo "   User: " . (isset($auth_settings['username']) ? $auth_settings['username'] : 'Unknown') . "\n";
        echo "   Sieve Host: " . (isset($auth_settings['sieve_config_host']) ? $auth_settings['sieve_config_host'] : 'NOT SET') . "\n";
        echo "   Sieve TLS: " . (isset($auth_settings['sieve_tls']) && $auth_settings['sieve_tls'] ? 'Yes' : 'No') . "\n";
        echo "\n";
    } else {
        echo "✅ No imap_auth_server_settings found\n\n";
    }
    
    // Check for other IMAP-related session data
    $imap_prefetched = $session->get('imap_prefetched_ids', array());
    if (!empty($imap_prefetched)) {
        echo "🔍 Found imap_prefetched_ids:\n";
        foreach ($imap_prefetched as $id) {
            echo "   - $id\n";
        }
        echo "\n";
    } else {
        echo "✅ No imap_prefetched_ids found\n\n";
    }
    
    // Check for any other session keys that might be relevant
    $relevant_keys = array(
        'imap_servers',
        'imap_server_list',
        'current_imap_server',
        'last_imap_server',
        'imap_cache',
        'user_data'
    );
    
    echo "🔍 Checking for other relevant session data:\n";
    foreach ($relevant_keys as $key) {
        $value = $session->get($key, null);
        if ($value !== null) {
            echo "   $key: " . (is_array($value) ? 'Array(' . count($value) . ' items)' : $value) . "\n";
        }
    }
    echo "\n";
    
    // Show all session keys for debugging
    echo "=== All Session Keys ===\n";
    foreach ($session_data as $key => $value) {
        if (is_array($value)) {
            echo "$key: Array(" . count($value) . " items)\n";
        } else {
            echo "$key: " . (is_string($value) && strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value) . "\n";
        }
    }
    
} else {
    echo "❌ User session is not active\n";
    echo "   Please log in to Cypht first.\n";
}

echo "\n=== Session Analysis Complete ===\n";
?> 