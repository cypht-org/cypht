<?php
/**
 * Clear Session Cache Script
 * 
 * This script clears the imap_auth_server_settings from the session
 * which is causing the server ID mismatch in spam reporting.
 */

// Include Cypht configuration
require_once 'lib/framework.php';

// Initialize configuration
$config = new Hm_Site_Config_File();

// Initialize session
$session = new Hm_User_Session($config);

if ($session->is_active()) {
    echo "✅ User session is active\n";
    
    // Check current imap_auth_server_settings
    $auth_settings = $session->get('imap_auth_server_settings', array());
    if (!empty($auth_settings)) {
        echo "⚠️  Found imap_auth_server_settings in session:\n";
        echo "   Server: " . (isset($auth_settings['server']) ? $auth_settings['server'] : 'Unknown') . "\n";
        echo "   User: " . (isset($auth_settings['username']) ? $auth_settings['username'] : 'Unknown') . "\n";
        echo "   Sieve Host: " . (isset($auth_settings['sieve_config_host']) ? $auth_settings['sieve_config_host'] : 'NOT SET') . "\n";
        
        // Clear the auth server settings
        $session->del('imap_auth_server_settings');
        echo "\n✅ Cleared imap_auth_server_settings from session\n";
        echo "   This should fix the server ID mismatch issue.\n";
    } else {
        echo "✅ No imap_auth_server_settings found in session\n";
    }
    
    // Also clear any other potentially problematic session data
    $session->del('imap_prefetched_ids');
    echo "✅ Cleared imap_prefetched_ids from session\n";
    
} else {
    echo "❌ User session is not active\n";
    echo "   Please log in to Cypht first.\n";
}

echo "\n=== Session Cache Clear Complete ===\n";
echo "Please log out and log back in to Cypht for the changes to take effect.\n";
?> 