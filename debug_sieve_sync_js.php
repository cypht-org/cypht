<?php
/**
 * Debug Script for Sieve Sync JavaScript Loading
 * Checks if the JavaScript functions are properly loaded
 */

define('APP_PATH', dirname(__FILE__) . '/');
define('VENDOR_PATH', APP_PATH . 'vendor/');
define('DEBUG_MODE', true);

require VENDOR_PATH . 'autoload.php';
require APP_PATH . 'lib/framework.php';

echo "=== Sieve Sync JavaScript Debug ===\n\n";

// Check if the JavaScript file exists and contains our functions
$js_file = APP_PATH . 'modules/imap/site.js';
if (file_exists($js_file)) {
    echo "✅ modules/imap/site.js exists\n";
    
    $js_content = file_get_contents($js_file);
    
    // Check for our functions
    $functions = [
        'triggerSieveSync',
        'updateSieveSyncStatus',
        'select_imap_folder'
    ];
    
    foreach ($functions as $func) {
        if (strpos($js_content, "function $func") !== false) {
            echo "✅ Function $func found in JavaScript\n";
        } else {
            echo "❌ Function $func NOT found in JavaScript\n";
        }
    }
    
    // Check for INBOX trigger
    if (strpos($js_content, 'path.includes("INBOX")') !== false) {
        echo "✅ INBOX trigger code found\n";
    } else {
        echo "❌ INBOX trigger code NOT found\n";
    }
    
    // Check for AJAX request
    if (strpos($js_content, 'ajax_sieve_sync') !== false) {
        echo "✅ AJAX sieve_sync request found\n";
    } else {
        echo "❌ AJAX sieve_sync request NOT found\n";
    }
    
} else {
    echo "❌ modules/imap/site.js does not exist\n";
}

echo "\n=== Browser Testing Commands ===\n";
echo "1. Open browser console (F12)\n";
echo "2. Run these commands:\n\n";

echo "// Check if functions are loaded\n";
echo "console.log('triggerSieveSync:', typeof triggerSieveSync);\n";
echo "console.log('updateSieveSyncStatus:', typeof updateSieveSyncStatus);\n\n";

echo "// Test status indicator\n";
echo "updateSieveSyncStatus('success', {synced: 4, failed: 0});\n\n";

echo "// Test manual trigger\n";
echo "triggerSieveSync();\n\n";

echo "// Check page context\n";
echo "console.log('Page:', getParam('page'));\n";
echo "console.log('Path:', getListPathParam());\n\n";

echo "// Test AJAX directly\n";
echo "Hm_Ajax.request([\n";
echo "    {'name': 'hm_ajax_hook', 'value': 'ajax_sieve_sync'}\n";
echo "], function(response) {\n";
echo "    console.log('AJAX Response:', response);\n";
echo "}, null, true);\n\n";

echo "=== Expected Results ===\n";
echo "✅ All functions should be defined\n";
echo "✅ Status indicator should appear\n";
echo "✅ AJAX request should complete\n";
echo "✅ Console should show debug messages\n\n";

echo "=== Troubleshooting ===\n";
echo "If functions are undefined:\n";
echo "1. Check if modules/imap/site.js is loaded\n";
echo "2. Check for JavaScript errors in console\n";
echo "3. Verify the file was saved correctly\n\n";

echo "If AJAX fails:\n";
echo "1. Check Network tab for failed requests\n";
echo "2. Verify ajax_sieve_sync endpoint is registered\n";
echo "3. Check if user is logged in\n\n";

echo "=== Debug Complete ===\n";
