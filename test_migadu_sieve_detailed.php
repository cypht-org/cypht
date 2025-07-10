<?php
/**
 * Detailed test for imap.migadu.com:4190
 */

echo "=== Detailed Test for imap.migadu.com:4190 ===\n\n";

// Include Composer's autoloader
require_once 'vendor/autoload.php';

$host = 'imap.migadu.com';
$port = 4190;
$user = 'test@notrewiki.com';
$pass = 'FuMxdfhCLD3vEuP5';

echo "Testing: $host:$port\n";
echo "User: $user\n\n";

// Test 1: Basic connection
echo "1. Testing basic connection...\n";
$connection = @fsockopen($host, $port, $errno, $errstr, 10);
if ($connection) {
    echo "   ✅ Connection successful\n";
    
    // Read server banner
    $banner = fgets($connection, 1024);
    if ($banner) {
        echo "   Server banner: " . trim($banner) . "\n";
    }
    
    fclose($connection);
} else {
    echo "   ❌ Connection failed: $errstr ($errno)\n";
    exit;
}

// Test 2: Try different authentication methods
echo "\n2. Testing authentication methods...\n";

$auth_methods = [
    ['method' => 'PLAIN', 'tls' => true],
    ['method' => 'PLAIN', 'tls' => false],
    ['method' => 'LOGIN', 'tls' => true],
    ['method' => 'LOGIN', 'tls' => false],
    ['method' => 'CRAM-MD5', 'tls' => true],
    ['method' => 'CRAM-MD5', 'tls' => false]
];

foreach ($auth_methods as $auth) {
    echo "   Testing {$auth['method']} with TLS " . ($auth['tls'] ? 'ON' : 'OFF') . "... ";
    
    try {
        $client = new PhpSieveManager\ManageSieve\Client($host, $port);
        $client->connect($user, $pass, $auth['tls'], "", $auth['method']);
        echo "✅ SUCCESS\n";
        
        // Try to get capabilities
        try {
            $capabilities = $client->getCapabilities();
            echo "      Capabilities: " . implode(', ', $capabilities) . "\n";
        } catch (Exception $e) {
            echo "      Could not get capabilities: " . $e->getMessage() . "\n";
        }
        
        $client->close();
        break; // Stop if one method works
        
    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
    }
}

// Test 3: Try with different username formats
echo "\n3. Testing different username formats...\n";

$username_variations = [
    'test@notrewiki.com',
    'test',
    'notrewiki.com\\test',
    'test@notrewiki.com@imap.migadu.com'
];

foreach ($username_variations as $username) {
    echo "   Testing username: $username... ";
    
    try {
        $client = new PhpSieveManager\ManageSieve\Client($host, $port);
        $client->connect($username, $pass, true, "", "PLAIN");
        echo "✅ SUCCESS\n";
        $client->close();
        break;
    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "\nIf any authentication method works, use that configuration in Cypht!\n"; 