<?php
/**
 * Test Sieve connection the way Cypht would do it
 */

echo "=== Testing Sieve Connection (Cypht Style) ===\n\n";

// Include Composer's autoloader
require_once 'vendor/autoload.php';

// Simulate Cypht's Sieve client factory
class Hm_Sieve_Client_Factory {
    public function init($user_config = null, $imap_account = null, $is_nux_supported = false) {
        if ($imap_account && !empty($imap_account['sieve_config_host'])) {
            list($sieve_host, $sieve_port, $sieve_tls) = $this->parse_sieve_config_host($imap_account);
            $client = new PhpSieveManager\ManageSieve\Client($sieve_host, $sieve_port);
            $client->connect($imap_account['user'], $imap_account['pass'], $sieve_tls, "", "PLAIN");
            return $client;
        } else {
            throw new Exception('Invalid config host');
        }
    }
    
    private function parse_sieve_config_host($imap_account) {
        $host = $imap_account['sieve_config_host'];
        $url = parse_url($host);
        if ($url === false) {
            return [$host, '4190', true];
        }
        $host = $url['host'] ?? $url['path'];
        $port = $url['port'] ?? '4190';
        if (isset($url['scheme'])) {
            $tls = $url['scheme'] == 'tls';
        } else {
            $tls = $imap_account['tls'] ?? true;
        }
        return [$host, $port, $tls];
    }
}

// Test configuration
$imap_account = [
    'name' => 'Migadu Test',
    'server' => 'imap.migadu.com',
    'port' => 993,
    'tls' => true,
    'user' => 'test@notrewiki.com',
    'pass' => 'FuMxdfhCLD3vEuP5',
    'sieve_config_host' => 'imap.migadu.com:4190'
];

echo "Testing with Cypht-style configuration:\n";
echo "IMAP Server: {$imap_account['server']}:{$imap_account['port']}\n";
echo "Sieve Host: {$imap_account['sieve_config_host']}\n";
echo "User: {$imap_account['user']}\n\n";

try {
    $factory = new Hm_Sieve_Client_Factory();
    $client = $factory->init(null, $imap_account, false);
    
    echo "✅ Cypht-style connection successful!\n";
    
    // Try to list scripts
    try {
        $scripts = $client->listScripts();
        echo "Available scripts: " . implode(', ', $scripts) . "\n";
    } catch (Exception $e) {
        echo "Could not list scripts: " . $e->getMessage() . "\n";
    }
    
    $client->close();
    
} catch (Exception $e) {
    echo "❌ Cypht-style connection failed: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n"; 