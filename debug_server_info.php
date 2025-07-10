<?php
/**
 * Debug Server Information Script
 * 
 * This script helps identify where server IDs come from in the session.
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
    
    echo "=== Looking for Server ID: 684b1017a7861 ===\n\n";
    
    // Check for imap_servers in session
    $imap_servers = $session->get('imap_servers', array());
    if (!empty($imap_servers)) {
        echo "🔍 Found imap_servers in session:\n";
        foreach ($imap_servers as $server_id => $server_config) {
            echo "   Server ID: $server_id\n";
            echo "   Name: " . (isset($server_config['name']) ? $server_config['name'] : 'Unknown') . "\n";
            echo "   Server: " . (isset($server_config['server']) ? $server_config['server'] : 'Unknown') . "\n";
            echo "   Sieve Host: " . (isset($server_config['sieve_config_host']) ? $server_config['sieve_config_host'] : 'NOT SET') . "\n";
            echo "\n";
            
            if ($server_id === '684b1017a7861') {
                echo "🎯 FOUND THE TARGET SERVER!\n";
                echo "   This server is configured in the session.\n\n";
            }
        }
    } else {
        echo "❌ No imap_servers found in session\n\n";
    }
    
    // Check for other IMAP-related session data
    $imap_list_servers = $session->get('imap_server_list', array());
    if (!empty($imap_list_servers)) {
        echo "🔍 Found imap_server_list in session:\n";
        foreach ($imap_list_servers as $server_id => $server_config) {
            echo "   Server ID: $server_id\n";
            echo "   Name: " . (isset($server_config['name']) ? $server_config['name'] : 'Unknown') . "\n";
            echo "   Server: " . (isset($server_config['server']) ? $server_config['server'] : 'Unknown') . "\n";
            echo "\n";
            
            if ($server_id === '684b1017a7861') {
                echo "🎯 FOUND THE TARGET SERVER in imap_server_list!\n\n";
            }
        }
    } else {
        echo "✅ No imap_server_list found in session\n\n";
    }
    
    // Check for any other session keys that might contain server info
    echo "🔍 Searching all session data for server ID 684b1017a7861:\n";
    $found = false;
    foreach ($session_data as $key => $value) {
        if (is_string($value) && strpos($value, '684b1017a7861') !== false) {
            echo "   Found in key '$key': $value\n";
            $found = true;
        } elseif (is_array($value)) {
            foreach ($value as $sub_key => $sub_value) {
                if (is_string($sub_value) && strpos($sub_value, '684b1017a7861') !== false) {
                    echo "   Found in key '$key[$sub_key]': $sub_value\n";
                    $found = true;
                }
            }
        }
    }
    
    if (!$found) {
        echo "   ❌ Server ID 684b1017a7861 not found in any session data\n";
    }
    
    echo "\n=== Current URL Analysis ===\n";
    echo "Your current URL shows server ID: 67adcf04539a1\n";
    echo "But logs show spam reports for server ID: 684b1017a7861\n";
    echo "This suggests you have multiple IMAP servers configured.\n\n";
    
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

echo "\n=== Debug Complete ===\n";
?> 