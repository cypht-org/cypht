<?php
/**
 * Simple Sieve Status Check
 */

echo "=== Simple Sieve Status Check ===\n\n";

// Your mail server details (from your configuration)
$sieve_host = "mail.postale.io";
$sieve_port = 4190;
$username = "test@notrewiki.com";
$password = "FuMxdfhCLD3vEuP5"; // You'll need to provide this

echo "🔍 Checking Sieve connection to: {$sieve_host}:{$sieve_port}\n";
echo "👤 Username: {$username}\n\n";

// Test basic connection
$connection = @fsockopen($sieve_host, $sieve_port, $errno, $errstr, 5);

if ($connection) {
    echo "✅ Basic connection to Sieve port successful\n";
    fclose($connection);
} else {
    echo "❌ Cannot connect to Sieve port: {$errstr} (Error {$errno})\n";
}

echo "\n=== Manual Steps to Check ===\n";
echo "1. Log into your mail server's web interface\n";
echo "2. Look for 'Sieve Filters' or 'Email Filtering'\n";
echo "3. Check if 'blocked_senders' script exists\n";
echo "4. Verify if it's set as the ACTIVE script\n";
echo "5. If not active, activate it\n\n";

echo "=== Expected Behavior ===\n";
echo "✅ When Sieve is working correctly:\n";
echo "   - Emails from blocked senders should NOT reach your inbox\n";
echo "   - They should be moved to Junk/Spam folder or rejected\n";
echo "   - You should NOT see them in Cypht at all\n\n";

echo "❌ Current behavior (problem):\n";
echo "   - Emails from blocked senders ARE reaching your inbox\n";
echo "   - Cypht detects them as blocked but it's too late\n";
echo "   - The mail server is not filtering them\n"; 