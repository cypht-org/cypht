<?php
/**
 * Test different ports on webmail.migadu.com
 */

echo "=== Testing Ports on webmail.migadu.com ===\n\n";

// Common ports to test
$ports = [
    4190, // Standard Sieve
    4191, // Alternative Sieve
    4192, // Alternative Sieve
    993,  // IMAP SSL
    995,  // POP3 SSL
    587,  // SMTP Submission
    465,  // SMTP SSL
    25,   // SMTP
    143,  // IMAP
    110,  // POP3
    80,   // HTTP
    443   // HTTPS
];

$host = 'webmail.migadu.com';

foreach ($ports as $port) {
    echo "Testing port $port... ";
    
    $connection = @fsockopen($host, $port, $errno, $errstr, 3);
    if ($connection) {
        echo "✅ OPEN\n";
        
        // Try to read the banner
        $banner = fgets($connection, 1024);
        if ($banner) {
            echo "    Banner: " . trim($banner) . "\n";
        }
        
        fclose($connection);
    } else {
        echo "❌ CLOSED ($errstr)\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "\nIf port 4190 is closed, Migadu might not support Sieve filters.\n";
echo "You may need to contact Migadu support to confirm Sieve support.\n"; 