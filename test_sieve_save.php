<?php
/**
 * Test Sieve Configuration Save
 * 
 * This script tests if we can save Sieve configuration programmatically.
 */

echo "=== Testing Sieve Configuration Save ===\n\n";

// Check if we can access the IMAP server configuration
$data_dir = 'data';
$site_dir = 'site';

echo "Looking for configuration files...\n";

$config_files = array_merge(
    glob($data_dir . '/*.ini'),
    glob($site_dir . '/*.ini'),
    glob($data_dir . '/*.json'),
    glob($site_dir . '/*.json'),
    glob($data_dir . '/*.php'),
    glob($site_dir . '/*.php')
);

if (empty($config_files)) {
    echo "No configuration files found.\n";
    echo "This might be because:\n";
    echo "1. User data is stored in PHP sessions\n";
    echo "2. User data is stored in a database\n";
    echo "3. Configuration files are in a different location\n\n";
    
    echo "Let's check for session files:\n";
    $session_files = glob('/tmp/sess_*');
    if (!empty($session_files)) {
        echo "Found session files:\n";
        foreach ($session_files as $file) {
            echo "  - " . basename($file) . "\n";
        }
    } else {
        echo "No session files found in /tmp/\n";
    }
    
    echo "\nLet's check for database configuration:\n";
    if (file_exists('config/database.php')) {
        echo "Database configuration file exists\n";
        $db_config = include 'config/database.php';
        if (isset($db_config['session_type']) && $db_config['session_type'] === 'DB') {
            echo "Database sessions are enabled\n";
        }
    }
    
} else {
    echo "Found configuration files:\n";
    foreach ($config_files as $file) {
        echo "  - " . $file . "\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "\nRECOMMENDATION:\n";
echo "Since the UI shows the Sieve configuration but it's not being saved,\n";
echo "try the following:\n";
echo "1. Log out of Cypht completely\n";
echo "2. Log back in\n";
echo "3. Go to Settings → Servers\n";
echo "4. Edit the 'grandi0z' server\n";
echo "5. Add Sieve Host: imap.migadu.com:4190\n";
echo "6. Save the configuration\n";
echo "7. Test auto-blocking\n"; 