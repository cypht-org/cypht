<?php
/**
 * Test Sieve Connection Script
 * 
 * This script tests Sieve connections for configured IMAP servers.
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
    
    echo "=== Testing Sieve Connections ===\n\n";
    
    foreach ($imap_servers as $server_id => $server_config) {
        echo "Testing Server: " . (isset($server_config['name']) ? $server_config['name'] : 'Unknown') . " (ID: $server_id)\n";
        echo "  IMAP Server: " . (isset($server_config['server']) ? $server_config['server'] : 'Unknown') . "\n";
        echo "  Sieve Host: " . (isset($server_config['sieve_config_host']) ? $server_config['sieve_config_host'] : 'NOT SET') . "\n";
        echo "  Sieve TLS: " . (isset($server_config['sieve_tls']) && $server_config['sieve_tls'] ? 'Yes' : 'No') . "\n";
        
        if (!isset($server_config['sieve_config_host']) || empty($server_config['sieve_config_host'])) {
            echo "  ❌ No Sieve host configured\n\n";
            continue;
        }
        
        // Test Sieve connection
        try {
            // Include necessary files
            require_once APP_PATH . 'modules/sievefilters/functions.php';
            require_once APP_PATH . 'modules/sievefilters/hm-sieve.php';
            
            // Parse Sieve host
            $sieve_info = parse_sieve_config_host($server_config);
            $host = $sieve_info[0];
            $port = $sieve_info[1];
            $tls = $sieve_info[2];
            
            echo "  🔍 Parsed Sieve info: Host=$host, Port=$port, TLS=" . ($tls ? 'Yes' : 'No') . "\n";
            
            // Test basic connection
            $connection_string = ($tls ? 'tls://' : 'tcp://') . $host . ':' . $port;
            echo "  🔗 Testing connection to: $connection_string\n";
            
            $socket = @fsockopen($host, $port, $errno, $errstr, 10);
            if ($socket) {
                echo "  ✅ Connection successful!\n";
                fclose($socket);
                
                // Try to get Sieve client factory
                $factory = get_sieve_client_factory($config);
                if ($factory) {
                    echo "  ✅ Sieve client factory created successfully\n";
                    
                    // Try to initialize client
                    $client = $factory->init($session, $server_config, false);
                    if ($client) {
                        echo "  ✅ Sieve client initialized successfully\n";
                        
                        // Try to list scripts
                        try {
                            $scripts = $client->listScripts();
                            echo "  ✅ Successfully listed " . count($scripts) . " Sieve scripts\n";
                            if (!empty($scripts)) {
                                echo "    Available scripts: " . implode(', ', $scripts) . "\n";
                            }
                        } catch (Exception $e) {
                            echo "  ⚠️  Could not list scripts: " . $e->getMessage() . "\n";
                        }
                        
                        $client->close();
                    } else {
                        echo "  ❌ Failed to initialize Sieve client\n";
                    }
                } else {
                    echo "  ❌ Failed to create Sieve client factory\n";
                }
            } else {
                echo "  ❌ Connection failed: $errstr (Error $errno)\n";
            }
            
        } catch (Exception $e) {
            echo "  ❌ Exception occurred: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
} else {
    echo "❌ User session is not active\n";
    echo "   Please log in to Cypht first.\n";
}

echo "\n=== Test Complete ===\n";
?> 