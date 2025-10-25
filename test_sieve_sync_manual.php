<?php
/**
 * Manual Sieve Sync Testing Script
 * Allows manual testing of the Sieve sync functionality
 */

define('APP_PATH', dirname(__FILE__) . '/');
define('VENDOR_PATH', APP_PATH . 'vendor/');
define('DEBUG_MODE', true);

require VENDOR_PATH . 'autoload.php';
require APP_PATH . 'lib/framework.php';

echo "=== Manual Sieve Sync Testing ===\n\n";

// Test 1: Check if classes are loaded
echo "1. Checking if classes are loaded...\n";
echo "   LocalBlockList: " . (class_exists('LocalBlockList') ? "✅ Loaded" : "❌ Missing") . "\n";
echo "   SieveQueue: " . (class_exists('SieveQueue') ? "✅ Loaded" : "❌ Missing") . "\n";
echo "   SieveSync: " . (class_exists('SieveSync') ? "✅ Loaded" : "❌ Missing") . "\n\n";

// Test 2: Check current queue status
echo "2. Current queue status...\n";
$queue_entries = SieveQueue::getAll();
echo "   Queue entries: " . count($queue_entries) . "\n";
if (!empty($queue_entries)) {
    foreach ($queue_entries as $entry) {
        echo "   - {$entry['sender_email']} (Status: {$entry['status']})\n";
    }
} else {
    echo "   No entries in queue\n";
}
echo "\n";

// Test 3: Check local block list
echo "3. Local block list status...\n";
$blocked_list = LocalBlockList::getAll();
echo "   Blocked senders: " . count($blocked_list) . "\n";
if (!empty($blocked_list)) {
    foreach ($blocked_list as $sender) {
        echo "   - $sender\n";
    }
} else {
    echo "   No blocked senders\n";
}
echo "\n";

// Test 4: Test SieveSync directly
echo "4. Testing SieveSync::processQueue()...\n";
try {
    $result = SieveSync::processQueue();
    echo "   Status: {$result['status']}\n";
    echo "   Message: {$result['message']}\n";
    echo "   Processed: {$result['processed']}\n";
    echo "   Synced: {$result['synced']}\n";
    echo "   Failed: {$result['failed']}\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Check AJAX endpoint
echo "5. Testing AJAX endpoint...\n";
echo "   Endpoint: ?page=ajax_sieve_sync\n";
echo "   Handler: Hm_Handler_sieve_sync\n";
echo "   Status: " . (class_exists('Hm_Handler_sieve_sync') ? "✅ Available" : "❌ Missing") . "\n\n";

// Test 6: Check JavaScript functions
echo "6. JavaScript testing...\n";
echo "   Open browser console and run:\n";
echo "   - triggerSieveSync()\n";
echo "   - updateSieveSyncStatus('success', {synced: 5, failed: 0})\n";
echo "   - updateSieveSyncStatus('error')\n";
echo "   - updateSieveSyncStatus('syncing')\n\n";

echo "=== Testing Complete ===\n";
