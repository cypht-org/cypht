<?php
/**
 * Fix Sieve Configuration Script
 * 
 * This script helps fix the Sieve configuration for IMAP servers.
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
    
    // Get IMAP servers from user configuration
    $imap_servers = $session->get('imap_servers', array());
    
    if (empty($imap_servers)) {
        echo "❌ No IMAP servers found in session\n";
        exit;
    }
    
    echo "=== Current IMAP Server Configuration ===\n\n";
    
    $fixed_servers = array();
    $changes_made = false;
    
    foreach ($imap_servers as $server_id => $server_config) {
        echo "Server ID: $server_id\n";
        echo "  Name: " . (isset($server_config['name']) ? $server_config['name'] : 'Unknown') . "\n";
        echo "  IMAP Server: " . (isset($server_config['server']) ? $server_config['server'] : 'Unknown') . "\n";
        echo "  Current Sieve Host: " . (isset($server_config['sieve_config_host']) ? $server_config['sieve_config_host'] : 'NOT SET') . "\n";
        echo "  Sieve TLS: " . (isset($server_config['sieve_tls']) && $server_config['sieve_tls'] ? 'Yes' : 'No') . "\n";
        
        // Check if Sieve host needs to be fixed
        $imap_host = isset($server_config['server']) ? $server_config['server'] : '';
        $current_sieve_host = isset($server_config['sieve_config_host']) ? $server_config['sieve_config_host'] : '';
        
        // Determine correct Sieve host based on IMAP server
        $correct_sieve_host = '';
        if ($imap_host === 'mail.postale.io') {
            $correct_sieve_host = 'mail.postale.io:4190';
        } elseif ($imap_host === 'imap.migadu.com') {
            $correct_sieve_host = 'imap.migadu.com:4190';
        } else {
            // Try to determine from services
            require_once APP_PATH . 'modules/nux/services.php';
            $sieve_info = get_sieve_host_from_services($imap_host);
            if ($sieve_info) {
                $correct_sieve_host = $sieve_info['host'] . ':' . $sieve_info['port'];
            }
        }
        
        if ($correct_sieve_host && $current_sieve_host !== $correct_sieve_host) {
            echo "  ❌ INCORRECT Sieve host! Should be: $correct_sieve_host\n";
            echo "  🔧 Fixing Sieve configuration...\n";
            
            // Update the server configuration
            $server_config['sieve_config_host'] = $correct_sieve_host;
            $server_config['sieve_tls'] = true; // Most Sieve servers use TLS
            
            $changes_made = true;
        } elseif ($correct_sieve_host) {
            echo "  ✅ Sieve host is correct\n";
        } else {
            echo "  ⚠️  Could not determine correct Sieve host for $imap_host\n";
        }
        
        $fixed_servers[$server_id] = $server_config;
        echo "\n";
    }
    
    if ($changes_made) {
        echo "=== Applying Fixes ===\n\n";
        
        // Update the session with fixed configurations
        $session->set('imap_servers', $fixed_servers);
        
        echo "✅ Sieve configurations have been updated in the session.\n";
        echo "📝 Please save your settings to make these changes permanent:\n";
        echo "   1. Go to Settings > Save Settings in Cypht\n";
        echo "   2. Or visit: ?page=save\n\n";
        
        echo "=== Updated Configuration ===\n\n";
        foreach ($fixed_servers as $server_id => $server_config) {
            echo "Server ID: $server_id\n";
            echo "  Name: " . (isset($server_config['name']) ? $server_config['name'] : 'Unknown') . "\n";
            echo "  IMAP Server: " . (isset($server_config['server']) ? $server_config['server'] : 'Unknown') . "\n";
            echo "  Sieve Host: " . (isset($server_config['sieve_config_host']) ? $server_config['sieve_config_host'] : 'NOT SET') . "\n";
            echo "  Sieve TLS: " . (isset($server_config['sieve_tls']) && $server_config['sieve_tls'] ? 'Yes' : 'No') . "\n\n";
        }
    } else {
        echo "✅ All Sieve configurations appear to be correct.\n";
    }
    
} else {
    echo "❌ User session is not active\n";
    echo "   Please log in to Cypht first.\n";
}

echo "\n=== Script Complete ===\n";
?> 