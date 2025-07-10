<?php
/**
 * Check Sieve Status
 * 
 * This script checks the Sieve status and verifies if the blocked_senders script is active.
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
    
    echo "=== Checking Sieve Status for Each Server ===\n\n";
    
    foreach ($imap_servers as $server_id => $server_config) {
        echo "🔍 Server: {$server_config['name']} (ID: {$server_id})\n";
        echo "   IMAP: {$server_config['server']}:{$server_config['port']}\n";
        
        if (isset($server_config['sieve_config_host'])) {
            echo "   Sieve: {$server_config['sieve_config_host']}\n";
            
            try {
                // Include necessary files
                require_once APP_PATH . 'modules/sievefilters/functions.php';
                require_once APP_PATH . 'modules/sievefilters/hm-sieve.php';
                
                // Initialize Sieve client
                $factory = get_sieve_client_factory($config);
                $client = $factory->init($session, $server_config, false);
                
                if (!$client) {
                    echo "   ❌ Failed to connect to Sieve server\n";
                    continue;
                }
                
                // Get list of scripts
                $scripts = $client->listScripts();
                echo "   📜 Available scripts: " . implode(', ', $scripts) . "\n";
                
                // Check if blocked_senders script exists
                if (in_array('blocked_senders', $scripts)) {
                    echo "   ✅ blocked_senders script found\n";
                    
                    // Get the script content
                    $script_content = $client->getScript('blocked_senders');
                    if (!empty($script_content)) {
                        echo "   ✅ Script has content (" . strlen($script_content) . " bytes)\n";
                        
                        // Check if script is active
                        $active_script = $client->getActiveScript();
                        if ($active_script === 'blocked_senders') {
                            echo "   ✅ blocked_senders script is ACTIVE\n";
                        } else {
                            echo "   ❌ blocked_senders script is NOT ACTIVE (active: {$active_script})\n";
                            echo "   💡 This is likely the problem! The script needs to be activated.\n";
                        }
                    } else {
                        echo "   ❌ Script is empty\n";
                    }
                } else {
                    echo "   ❌ blocked_senders script not found\n";
                }
                
                // Test Sieve connection
                echo "   🔗 Testing Sieve connection...\n";
                if ($client->isConnected()) {
                    echo "   ✅ Sieve connection is working\n";
                } else {
                    echo "   ❌ Sieve connection failed\n";
                }
                
            } catch (Exception $e) {
                echo "   ❌ Error: " . $e->getMessage() . "\n";
            }
            
        } else {
            echo "   ❌ No Sieve configuration\n";
        }
        
        echo "\n";
    }
    
    echo "=== Check Complete ===\n";
    echo "\nIf the blocked_senders script is not active, that's why emails are reaching your inbox.\n";
    
} else {
    echo "❌ User session is not active. Please log into Cypht first.\n";
} 