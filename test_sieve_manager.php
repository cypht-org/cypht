<?php
/**
 * Test script to check if PhpSieveManager is properly loaded
 */

// Include Composer's autoloader
require_once 'vendor/autoload.php';

echo "=== PhpSieveManager Test ===\n\n";

// Test if the class exists
if (class_exists('PhpSieveManager\ManageSieve\Client')) {
    echo "✅ PhpSieveManager\\ManageSieve\\Client class is available\n";
} else {
    echo "❌ PhpSieveManager\\ManageSieve\\Client class is NOT available\n";
}

// Test if the FilterFactory exists
if (class_exists('PhpSieveManager\Filters\FilterFactory')) {
    echo "✅ PhpSieveManager\\Filters\\FilterFactory class is available\n";
} else {
    echo "❌ PhpSieveManager\\Filters\\FilterFactory class is NOT available\n";
}

// Test if the FilterCriteria exists
if (class_exists('PhpSieveManager\Filters\FilterCriteria')) {
    echo "✅ PhpSieveManager\\Filters\\FilterCriteria class is available\n";
} else {
    echo "❌ PhpSieveManager\\Filters\\FilterCriteria class is NOT available\n";
}

// Test if the Condition class exists
if (class_exists('PhpSieveManager\Filters\Condition')) {
    echo "✅ PhpSieveManager\\Filters\\Condition class is available\n";
} else {
    echo "❌ PhpSieveManager\\Filters\\Condition class is NOT available\n";
}

echo "\n=== Test Complete ===\n"; 