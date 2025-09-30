<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for SieveConnectionManager
 */
class Hm_Test_sieve_connection_manager extends TestCase {

    public function setUp(): void {
        require 'bootstrap.php';
        SieveConnectionManager::closeAllConnections();
        
        SieveConnectionManager::setTimeout(10);
    }

    public function tearDown(): void {
        SieveConnectionManager::closeAllConnections();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setConfig_valid() {
        $config = [
            'server1' => [
                'host' => 'imap.example.com',
                'port' => 4190,
                'username' => 'user@example.com',
                'password' => 'password123',
                'secure' => true,
                'authType' => 'PLAIN'
            ]
        ];
        
        SieveConnectionManager::setConfig($config);
        $this->assertEquals($config, SieveConnectionManager::getConfig());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setConfig_empty() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Configuration cannot be empty.");
        
        SieveConnectionManager::setConfig([]);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setConfig_missing_required_fields() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Each server configuration must include 'host', 'port', 'username', and 'password'.");
        
        $config = [
            'server1' => [
                'host' => 'imap.example.com',
                'port' => 4190,
            ]
        ];
        
        SieveConnectionManager::setConfig($config);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setConfig_invalid_types() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid types in server configuration for 'server1'.");
        
        $config = [
            'server1' => [
                'host' => 'imap.example.com',
                'port' => '4190', // should be int
                'username' => 'user@example.com',
                'password' => 'password123'
            ]
        ];
        
        SieveConnectionManager::setConfig($config);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setTimeout() {
        SieveConnectionManager::setTimeout(300);
        
        // We can't directly test the timeout value since it's private,
        // but we can verify the method doesn't throw an error
        $this->assertTrue(true);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getConnection_no_config() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No configuration found for 'nonexistent'");
        
        SieveConnectionManager::getConnection('nonexistent');
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_closeConnection() {
        SieveConnectionManager::closeConnection('nonexistent');
        
        $this->assertTrue(true);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_closeAllConnections() {
        SieveConnectionManager::closeAllConnections();
        
        $this->assertTrue(true);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_config_merging() {
        $config1 = [
            'server1' => [
                'host' => 'imap.example.com',
                'port' => 4190,
                'username' => 'user@example.com',
                'password' => 'password123'
            ]
        ];
        
        SieveConnectionManager::setConfig($config1);
        
        $config2 = [
            'server1' => [
                'host' => 'imap.example.com',
                'port' => 4190,
                'username' => 'user@example.com',
                'password' => 'password123',
                'secure' => true
            ]
        ];
        
        SieveConnectionManager::setConfig($config2);
        
        $finalConfig = SieveConnectionManager::getConfig();
        $this->assertTrue($finalConfig['server1']['secure']);
    }
}
