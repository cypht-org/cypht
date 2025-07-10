<?php
/**
 * Test Migadu Sieve configurations
 */

echo "=== Testing Migadu Sieve Configurations ===\n\n";

// Include Composer's autoloader
require_once 'vendor/autoload.php';

// Test configurations
$configs = [
    'sieve.migadu.com:4190',
    'sieve.migadu.com:4191',
    'sieve.migadu.com:4192',
    'webmail.migadu.com:4190',
    'mail.migadu.com:4190',
    'sieve.mail.migadu.com:4190',
    'sieve.webmail.migadu.com:4190',
    'imap.migadu.com:4190',  // Added this one
    'imap.migadu.com:4191',  // And this variation
    'imap.migadu.com:4192',  // And this variation
    'smtp.migadu.com:4190',  // Try SMTP server too
    'mail.migadu.com:4191',  // Alternative port
    'mail.migadu.com:4192'   // Alternative port
];

$credentials = [
    'user' => 'test@notrewiki.com',
    'pass' => 'FuMxdfhCLD3vEuP5'
];

foreach ($configs as $config) {
    echo "Testing: $config\n";
    
    // Parse host and port
    $parts = explode(':', $config);
    $host = $parts[0];
    $port = isset($parts[1]) ? $parts[1] : 4190;
    
    // Test basic connection
    $connection = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($connection) {
        echo "  ✅ Basic connection successful\n";
        fclose($connection);
        
        // Test Sieve client
        try {
            $client = new PhpSieveManager\ManageSieve\Client($host, $port);
            $client->connect($credentials['user'], $credentials['pass'], true, "", "PLAIN");
            echo "  ✅ Sieve authentication successful\n";
            $client->close();
        } catch (Exception $e) {
            echo "  ❌ Sieve authentication failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ❌ Connection failed: $errstr ($errno)\n";
    }
    echo "\n";
}

echo "=== Test Complete ===\n";
echo "\nIf any configuration shows '✅ Sieve authentication successful', use that one!\n"; 