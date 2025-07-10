<?php
/**
 * Test script for auto-blocking system
 * Run this script to verify that the auto-blocking system is properly configured
 */

// Define constants for testing
define('DEBUG_MODE', true);
define('APP_PATH', __DIR__ . '/');

// Include Composer autoloader
if (file_exists(APP_PATH . 'vendor/autoload.php')) {
    require_once APP_PATH . 'vendor/autoload.php';
} else {
    echo "Error: Composer autoloader not found. Please run 'composer install' first.\n";
    exit(1);
}

// Include framework functions first
require_once APP_PATH . 'lib/framework.php';

// Define missing functions if they don't exist
if (!function_exists('hm_exists')) {
    function hm_exists($name) {
        return function_exists($name);
    }
}

// Include the necessary files
require_once APP_PATH . 'modules/imap/spam_report_utils.php';

echo "=== Auto-Blocking System Test ===\n\n";

// Test 1: Check if sievefilters module is available
echo "1. Checking sievefilters module availability...\n";
if (function_exists('is_sievefilters_module_available')) {
    $available = is_sievefilters_module_available();
    echo "   Result: " . ($available ? "PASS" : "FAIL") . "\n";
    echo "   Details: " . ($available ? "All required functions are available" : "Missing required functions") . "\n";
} else {
    echo "   Result: FAIL\n";
    echo "   Details: is_sievefilters_module_available function not found\n";
}

// Test 2: Check individual functions
echo "\n2. Checking individual required functions...\n";
$required_functions = [
    'get_sieve_client_factory',
    'prepare_sieve_script', 
    'block_filter',
    'generate_main_script',
    'save_main_script'
];

foreach ($required_functions as $func) {
    $exists = function_exists($func);
    echo "   $func: " . ($exists ? "PASS" : "FAIL") . "\n";
}

// Test 3: Check PhpSieveManager classes
echo "\n3. Checking PhpSieveManager classes...\n";
try {
    $filter = \PhpSieveManager\Filters\FilterFactory::create('test');
    echo "   PhpSieveManager\Filters\FilterFactory: PASS\n";
} catch (Exception $e) {
    echo "   PhpSieveManager\Filters\FilterFactory: FAIL - " . $e->getMessage() . "\n";
}

// Test 4: Test email extraction function
echo "\n4. Testing email extraction function...\n";
$test_headers = [
    'From' => 'John Doe <john@example.com>'
];
$email = extract_sender_email_from_headers($test_headers);
echo "   Email extraction: " . ($email === 'john@example.com' ? "PASS" : "FAIL") . "\n";
echo "   Extracted email: $email\n";

// Test 5: Test domain extraction function
echo "\n5. Testing domain extraction function...\n";
$domain = get_domain('john@example.com');
echo "   Domain extraction: " . ($domain === 'example.com' ? "PASS" : "FAIL") . "\n";
echo "   Extracted domain: $domain\n";

// Test 6: Test action mapping function
echo "\n6. Testing action mapping function...\n";
$action = map_auto_block_action_to_sieve('move_to_junk', null, 'test');
echo "   Action mapping: " . ($action === 'blocked' ? "PASS" : "FAIL") . "\n";
echo "   Mapped action: $action\n";

echo "\n=== Test Complete ===\n";
echo "\nIf all tests pass, the auto-blocking system should work properly.\n";
echo "If any tests fail, check the configuration and module dependencies.\n"; 