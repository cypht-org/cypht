<?php
/**
 * Direct Test of Sieve Sync Handler
 * Tests the backend handler directly without AJAX
 */

define('APP_PATH', dirname(__FILE__) . '/');
define('VENDOR_PATH', APP_PATH . 'vendor/');
define('DEBUG_MODE', true);

require VENDOR_PATH . 'autoload.php';
require APP_PATH . 'lib/framework.php';

echo "=== Direct Sieve Sync Handler Test ===\n\n";

// Test 1: Load framework and modules
echo "1. Loading framework and modules...\n";
require APP_PATH . 'modules/imap/modules.php';
echo "   ✅ Framework and modules loaded\n";

// Test 2: Check if handler class exists
echo "\n2. Checking handler class...\n";
if (class_exists('Hm_Handler_sieve_sync')) {
    echo "   ✅ Hm_Handler_sieve_sync class exists\n";
} else {
    echo "   ❌ Hm_Handler_sieve_sync class NOT found\n";
    exit;
}

// Test 3: Check if SieveSync class exists
echo "\n3. Checking SieveSync class...\n";
if (class_exists('SieveSync')) {
    echo "   ✅ SieveSync class exists\n";
} else {
    echo "   ❌ SieveSync class NOT found\n";
    exit;
}

// Test 4: Test SieveSync::processQueue directly
echo "\n4. Testing SieveSync::processQueue()...\n";
try {
    $result = SieveSync::processQueue();
    echo "   Status: {$result['status']}\n";
    echo "   Message: {$result['message']}\n";
    echo "   Processed: {$result['processed']}\n";
    echo "   Synced: {$result['synced']}\n";
    echo "   Failed: {$result['failed']}\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

// Test 5: Check queue status
echo "\n5. Current queue status...\n";
$queue_entries = SieveQueue::getAll();
echo "   Queue entries: " . count($queue_entries) . "\n";
if (!empty($queue_entries)) {
    foreach ($queue_entries as $entry) {
        echo "   - {$entry['sender_email']} (Status: {$entry['status']})\n";
    }
}

// Test 6: Check AJAX endpoint registration
echo "\n6. Checking AJAX endpoint registration...\n";
$setup_file = APP_PATH . 'modules/imap/setup.php';
if (file_exists($setup_file)) {
    $setup_content = file_get_contents($setup_file);
    if (strpos($setup_content, 'ajax_sieve_sync') !== false) {
        echo "   ✅ ajax_sieve_sync endpoint found in setup.php\n";
    } else {
        echo "   ❌ ajax_sieve_sync endpoint NOT found in setup.php\n";
    }
    
    if (strpos($setup_content, 'sieve_sync') !== false) {
        echo "   ✅ sieve_sync handler found in setup.php\n";
    } else {
        echo "   ❌ sieve_sync handler NOT found in setup.php\n";
    }
} else {
    echo "   ❌ setup.php file not found\n";
}

echo "\n=== Test Complete ===\n";
