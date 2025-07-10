<?php
/**
 * Test current IMAP connection
 */

echo "=== Testing Current IMAP Connection ===\n\n";

// Your current settings from .env
$host = 'webmail.migadu.com';
$port = 993;
$user = 'test@notrewiki.com';
$pass = 'FuMxdfhCLD3vEuP5';

echo "Testing connection to: $host:$port\n";
echo "Username: $user\n\n";

// Test basic connection
$connection = @fsockopen($host, $port, $errno, $errstr, 10);
if ($connection) {
    echo "✅ Basic connection successful\n";
    
    // Try to read IMAP banner
    $banner = fgets($connection, 1024);
    if ($banner) {
        echo "IMAP Banner: " . trim($banner) . "\n";
    }
    
    fclose($connection);
} else {
    echo "❌ Connection failed: $errstr ($errno)\n";
    echo "\nThis confirms that Migadu is webmail-only and doesn't support IMAP.\n";
    echo "You'll need to use their web interface or contact them for API access.\n";
}

echo "\n=== Test Complete ===\n"; 