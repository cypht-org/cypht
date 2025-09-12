<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for SieveService
 */
class Hm_Test_sieve_service extends TestCase {

    private $mockCache;

    public function setUp(): void {
        require 'bootstrap.php';

        $this->mockCache = new MockCache();
        
        $config = [
            'server1' => [
                'host' => 'imap.example.com',
                'port' => 4190,
                'username' => 'user@example.com',
                'password' => 'password123',
                'secure' => true
            ]
        ];
        
        SieveService::init($this->mockCache, $config);
    }

    public function tearDown(): void {
        $this->mockCache->clear();
        SieveConnectionManager::closeAllConnections();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_init() {
        $cache = new MockCache();
        $config = [
            'test_server' => [
                'host' => 'test.example.com',
                'port' => 4190,
                'username' => 'test@example.com',
                'password' => 'testpass'
            ]
        ];
        
        // We need to use reflection to reset the config since there's no public method to clear it
        $reflection = new ReflectionClass('SieveConnectionManager');
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue([]);
        
        SieveService::init($cache, $config);
        
        $this->assertEquals($config, SieveConnectionManager::getConfig());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setCacheTTL() {
        SieveService::setCacheTTL(1800);
        
        $this->assertTrue(true);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setConnectionTimeout() {
        SieveService::setConnectionTimeout(300);
        
        $this->assertTrue(true);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hasAccounts() {
        $this->assertTrue(SieveService::hasAccounts());
    }
    
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_hasAccounts_empty_config() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Configuration cannot be empty.");
        
        SieveService::init($this->mockCache, []);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_clearScriptCache() {
        SieveScriptCache::cacheScript('server1', 'test.sieve', 'test content');
        
        $this->assertEquals('test content', SieveScriptCache::getCachedScript('server1', 'test.sieve'));
        
        $result = SieveService::clearScriptCache('server1', 'test.sieve');
        $this->assertTrue($result);
        
        $this->assertFalse(SieveScriptCache::getCachedScript('server1', 'test.sieve'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_service_methods_signature() {
        // Test that all expected methods exist and are callable
        $this->assertTrue(method_exists('SieveService', 'getScript'));
        $this->assertTrue(method_exists('SieveService', 'listScripts'));
        $this->assertTrue(method_exists('SieveService', 'putScript'));
        $this->assertTrue(method_exists('SieveService', 'activateScript'));
        $this->assertTrue(method_exists('SieveService', 'removeScripts'));
        $this->assertTrue(method_exists('SieveService', 'closeConnection'));
        $this->assertTrue(method_exists('SieveService', 'renameScript'));
        $this->assertTrue(method_exists('SieveService', 'getCapabilities'));
        $this->assertTrue(method_exists('SieveService', 'getConnection'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_exception_handling() {
        $this->expectException(Exception::class);
        SieveService::getConnection('invalid_server');
    }
}
