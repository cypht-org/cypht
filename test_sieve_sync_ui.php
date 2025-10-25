<?php
/**
 * Test Script for Sieve Sync UI
 * Creates test data and provides testing instructions
 */

define('APP_PATH', dirname(__FILE__) . '/');
define('VENDOR_PATH', APP_PATH . 'vendor/');
define('DEBUG_MODE', true);

require VENDOR_PATH . 'autoload.php';
require APP_PATH . 'lib/framework.php';

echo "=== Sieve Sync UI Testing Setup ===\n\n";

// 1. Add test blocked senders to local block list
echo "1. Adding test blocked senders to local block list...\n";
$test_senders = [
    'test1@spammer.com',
    'test2@malicious.org', 
    'test3@phishing.net',
    'test4@scam.co.uk'
];

foreach ($test_senders as $sender) {
    $result = LocalBlockList::add($sender);
    echo "   Added: $sender - " . ($result ? "✅ Success" : "❌ Failed") . "\n";
}

// 2. Add test entries to Sieve queue
echo "\n2. Adding test entries to Sieve queue...\n";
$test_user = 'test@notrewiki.com'; // Replace with your actual username
$test_server_id = '686accf7cadcf'; // Replace with your actual server ID

foreach ($test_senders as $sender) {
    $entry_id = SieveQueue::add($test_user, $sender, [
        'username' => $test_user,
        'imap_server_id' => $test_server_id,
        'spam_reason' => 'Test spam report',
        'message_uid' => '999',
        'folder' => 'INBOX',
        'block_scope' => 'sender',
        'block_action' => 'move_to_junk'
    ]);
    echo "   Queued: $sender - Entry ID: $entry_id\n";
}

// 3. Show current state
echo "\n3. Current state:\n";
$blocked_list = LocalBlockList::getAll();
echo "   Local blocked senders: " . count($blocked_list) . "\n";
echo "   Blocked list: " . json_encode($blocked_list) . "\n";

$queue_entries = SieveQueue::getAll();
echo "   Queue entries: " . count($queue_entries) . "\n";

echo "\n=== Testing Instructions ===\n";
echo "1. Open Cypht in your browser\n";
echo "2. Navigate to INBOX folder\n";
echo "3. Watch for Sieve sync status indicator (top-right corner)\n";
echo "4. Check browser console for debug messages\n";
echo "5. Check debug logs: tail -f logs/debug.log | grep 'SieveSync'\n\n";

echo "=== Expected Behavior ===\n";
echo "✅ Status indicator should appear when INBOX loads\n";
echo "✅ Should show 'Syncing...' then 'Synced X rules' or error\n";
echo "✅ Console should show 'Triggering Sieve sync for INBOX folder'\n";
echo "✅ Debug logs should show configuration discovery and sync process\n\n";

echo "=== Manual Testing Commands ===\n";
echo "Test AJAX endpoint directly:\n";
echo "curl -X POST 'http://localhost/cypht/?page=ajax_sieve_sync' \\\n";
echo "  -H 'Content-Type: application/x-www-form-urlencoded' \\\n";
echo "  -d 'hm_page_key=your_page_key'\n\n";

echo "Check queue status:\n";
echo "php -r \"require 'lib/framework.php'; print_r(SieveQueue::getAll());\"\n\n";

echo "=== Troubleshooting ===\n";
echo "If sync doesn't trigger:\n";
echo "1. Check browser console for JavaScript errors\n";
echo "2. Verify you're on message_list page with INBOX folder\n";
echo "3. Check if rate limiting is active (30min cooldown)\n";
echo "4. Verify AJAX endpoint is registered in setup.php\n\n";

echo "If sync fails:\n";
echo "1. Check debug logs for configuration errors\n";
echo "2. Verify IMAP server supports Sieve (Migadu, Dovecot, Cyrus)\n";
echo "3. Check if Sieve credentials are correct\n";
echo "4. Test Sieve connection manually\n\n";

echo "=== Test Complete ===\n";
