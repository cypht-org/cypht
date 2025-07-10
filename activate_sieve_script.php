<?php
/**
 * Activate Sieve Script
 * 
 * This script activates the blocked_senders Sieve script on the mail server.
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
    
    echo "=== Activating Sieve Scripts ===\n\n";
    
    foreach ($imap_servers as $server_id => $server_config) {
        echo "🔍 Server: {$server_config['name']} (ID: {$server_id})\n";
        
        if (!isset($server_config['sieve_config_host'])) {
            echo "   ❌ No Sieve configuration\n\n";
            continue;
        }
        
        try {
            // Include necessary files
            require_once APP_PATH . 'modules/sievefilters/functions.php';
            require_once APP_PATH . 'modules/sievefilters/hm-sieve.php';
            
            // Initialize Sieve client
            $factory = get_sieve_client_factory($config);
            $client = $factory->init($session, $server_config, false);
            
            if (!$client) {
                echo "   ❌ Failed to connect to Sieve server\n\n";
                continue;
            }
            
            // Check current active script
            $current_active = $client->getActiveScript();
            echo "   📜 Current active script: " . ($current_active ?: 'none') . "\n";
            
            // Check if blocked_senders script exists
            $scripts = $client->listScripts();
            if (!in_array('blocked_senders', $scripts)) {
                echo "   ❌ blocked_senders script not found\n";
                echo "   📜 Available scripts: " . implode(', ', $scripts) . "\n\n";
                continue;
            }
            
            echo "   ✅ blocked_senders script found\n";
            
            // Activate the blocked_senders script
            if ($current_active !== 'blocked_senders') {
                echo "   🔄 Activating blocked_senders script...\n";
                $result = $client->setActiveScript('blocked_senders');
                
                if ($result) {
                    echo "   ✅ Successfully activated blocked_senders script!\n";
                } else {
                    echo "   ❌ Failed to activate blocked_senders script\n";
                }
            } else {
                echo "   ✅ blocked_senders script is already active\n";
            }
            
            // Verify activation
            $new_active = $client->getActiveScript();
            echo "   📜 New active script: " . ($new_active ?: 'none') . "\n";
            
        } catch (Exception $e) {
            echo "   ❌ Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    echo "=== Activation Complete ===\n";
    echo "\nNow test by sending an email from a blocked sender.\n";
    echo "The email should NOT reach your inbox if Sieve is working correctly.\n";
    
} else {
    echo "❌ User session is not active. Please log into Cypht first.\n";
} 