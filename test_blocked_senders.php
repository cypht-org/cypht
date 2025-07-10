<?php
/**
 * Test Blocked Senders List
 * 
 * This script tests the blocked senders functionality to see what senders are currently blocked.
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
    
    echo "=== Testing Blocked Senders for Each Server ===\n\n";
    
    foreach ($imap_servers as $server_id => $server_config) {
        echo "🔍 Server: {$server_config['name']} (ID: {$server_id})\n";
        echo "   IMAP: {$server_config['server']}:{$server_config['port']}\n";
        
        if (isset($server_config['sieve_config_host'])) {
            echo "   Sieve: {$server_config['sieve_config_host']}\n";
            
            // Test blocked senders list
            $blocked_senders = get_blocked_senders_list($session, $server_id);
            
            if (empty($blocked_senders)) {
                echo "   ❌ No blocked senders found\n";
            } else {
                echo "   ✅ Found " . count($blocked_senders) . " blocked senders:\n";
                foreach (array_slice($blocked_senders, 0, 10) as $sender) {
                    echo "      - {$sender}\n";
                }
                if (count($blocked_senders) > 10) {
                    echo "      ... and " . (count($blocked_senders) - 10) . " more\n";
                }
            }
        } else {
            echo "   ❌ No Sieve configuration\n";
        }
        
        echo "\n";
    }
    
    echo "=== Test Complete ===\n";
    echo "\nNow try receiving an email from a blocked sender and check the debug logs.\n";
    
} else {
    echo "❌ User session is not active. Please log into Cypht first.\n";
} 