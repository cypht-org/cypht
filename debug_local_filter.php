<?php
/**
 * Debug Local Filter
 * Quick script to check if local filtering is working
 */

define('APP_PATH', __DIR__ . '/');
define('DEBUG_MODE', true);
define('VENDOR_PATH', APP_PATH.'vendor/');

require APP_PATH.'lib/framework.php';

echo "=== Local Filter Debug ===\n\n";

// Check if LocalBlockList exists
if (class_exists('LocalBlockList')) {
    echo "✅ LocalBlockList class loaded\n";
} else {
    echo "❌ LocalBlockList class NOT found\n";
    exit(1);
}

// Get blocked senders
$blocked = LocalBlockList::getAll();
echo "\n📋 Blocked Senders:\n";
if (empty($blocked)) {
    echo "  (empty)\n";
} else {
    foreach ($blocked as $email) {
        echo "  - $email\n";
    }
}

// Test with a known blocked sender
if (!empty($blocked)) {
    $test_email = $blocked[0];
    echo "\n🧪 Testing: $test_email\n";
    
    if (LocalBlockList::exists($test_email)) {
        echo "  ✅ EXISTS check: TRUE\n";
    } else {
        echo "  ❌ EXISTS check: FALSE\n";
    }
    
    // Try with different formats
    $test_variations = [
        $test_email,
        strtoupper($test_email),
        "Name <$test_email>",
        "<$test_email>"
    ];
    
    echo "\n📧 Email format variations:\n";
    foreach ($test_variations as $variant) {
        $normalized = strtolower(trim($variant));
        if (preg_match('/<([^>]+)>/', $normalized, $matches)) {
            $normalized = $matches[1];
        }
        $exists = LocalBlockList::exists($normalized);
        echo "  '$variant' → '$normalized' → " . ($exists ? '✅ BLOCKED' : '❌ NOT BLOCKED') . "\n";
    }
}

// Check data file
$file_path = APP_PATH . 'data/local_blocked.json';
echo "\n📁 File: $file_path\n";
if (file_exists($file_path)) {
    echo "  ✅ File exists\n";
    $content = file_get_contents($file_path);
    echo "  Size: " . strlen($content) . " bytes\n";
    echo "  Content:\n";
    echo "  " . str_replace("\n", "\n  ", $content) . "\n";
} else {
    echo "  ❌ File NOT found\n";
}

echo "\n=== Done ===\n";

