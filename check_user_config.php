<?php
/**
 * Check User Configuration Script
 * 
 * This script uses Cypht's framework to check user configuration.
 */

// Set up basic environment
define('APP_PATH', '');
define('VENDOR_PATH', APP_PATH.'vendor/');
define('CONFIG_PATH', APP_PATH.'config/');
define('WEB_ROOT', '');
define('ASSETS_THEMES_ROOT', '');
define('DEBUG_MODE', true);
define('CACHE_ID', '');
define('SITE_ID', '');
define('JS_HASH', '');
define('CSS_HASH', '');
define('ASSETS_PATH', APP_PATH.'assets/');

// Include required files
require VENDOR_PATH.'autoload.php';
require APP_PATH.'lib/framework.php';

// Initialize environment
$environment = Hm_Environment::getInstance();
$environment->load();

// Get configuration
$config = new Hm_Site_Config_File();

echo "=== User Configuration Check ===\n\n";

// Check session type
$session_type = $config->get('session_type', 'PHP');
echo "Session type: $session_type\n\n";

// Try to get user configuration
try {
    // Initialize session
    $session = new Hm_User_Session($config);
    $user_config = new Hm_User_Config($config, $session);
    
    if ($session->is_active()) {
        echo "✅ User session is active\n\n";
        
        // Get IMAP servers
        $imap_servers = $user_config->get('imap_servers', array());
        
        if (empty($imap_servers)) {
            echo "⚠️  No IMAP servers configured\n";
        } else {
            echo "📧 IMAP Servers:\n";
            foreach ($imap_servers as $server_id => $server_config) {
                echo "  Server: " . (isset($server_config['name']) ? $server_config['name'] : 'Unknown') . " (ID: $server_id)\n";
                echo "    Sieve Host: " . (isset($server_config['sieve_config_host']) ? $server_config['sieve_config_host'] : 'NOT CONFIGURED') . "\n";
                if (isset($server_config['sieve_config_host']) && !empty($server_config['sieve_config_host'])) {
                    echo "    ✅ Sieve is configured\n";
                } else {
                    echo "    ❌ Sieve is NOT configured\n";
                }
                echo "\n";
            }
        }
        
        // Check auto-block settings
        $auto_block_enabled = $user_config->get('enable_auto_block_spam', false);
        echo "Auto-block enabled: " . ($auto_block_enabled ? '✅ Yes' : '❌ No') . "\n";
        
        $sieve_filters_enabled = $user_config->get('enable_sieve_filter_setting', true);
        echo "Sieve filters enabled: " . ($sieve_filters_enabled ? '✅ Yes' : '❌ No') . "\n";
        
    } else {
        echo "❌ User session is not active\n";
        echo "   Please log in to Cypht first.\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error accessing user configuration: " . $e->getMessage() . "\n\n";
}

echo "\n=== Configuration Check Complete ===\n"; 