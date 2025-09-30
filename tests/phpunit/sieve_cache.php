<?php

use PHPUnit\Framework\TestCase;
/**
 * Tests for SieveCache
 */
class Hm_Test_sieve_cache extends TestCase {

    private $mockCache;

    public function setUp(): void {
        require 'bootstrap.php';
        
        $this->mockCache = new MockCache();
        SieveScriptCache::setCache($this->mockCache);
        
        SieveScriptCache::setCacheTTL(3600);
    }

    public function tearDown(): void {
        $this->mockCache->clear();
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setCache() {
        $cache = new MockCache();
        SieveScriptCache::setCache($cache);
        
        $this->assertTrue(SieveScriptCache::cacheScript('test_key', 'test_script', 'test_content'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setCache_invalid() {
        $originalCache = new MockCache();
        SieveScriptCache::setCache($originalCache);
        
        SieveScriptCache::setCache("invalid_cache");
        
        $this->assertTrue(SieveScriptCache::cacheScript('test_key', 'test_script', 'test_content'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_setCacheTTL() {
        SieveScriptCache::setCacheTTL(1800);
        
        SieveScriptCache::cacheScript('test_key', 'test_script', 'test_content');
        
        $this->assertEquals('test_content', SieveScriptCache::getCachedScript('test_key', 'test_script'));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_cacheScript_and_getCachedScript() {
        $key = 'test_server';
        $scriptName = 'vacation.sieve';
        $scriptContent = 'require "vacation"; vacation :days 10 "I\'m on vacation";';
        
        $result = SieveScriptCache::cacheScript($key, $scriptName, $scriptContent);
        $this->assertTrue($result);
        
        $cached = SieveScriptCache::getCachedScript($key, $scriptName);
        $this->assertEquals($scriptContent, $cached);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getCachedScript_not_found() {
        $result = SieveScriptCache::getCachedScript('nonexistent_key', 'nonexistent_script');
        $this->assertFalse($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_getCachedScript_expired() {
        // Set a very short TTL
        SieveScriptCache::setCacheTTL(1);
        
        $key = 'test_server';
        $scriptName = 'test.sieve';
        $scriptContent = 'test content';
        
        SieveScriptCache::cacheScript($key, $scriptName, $scriptContent);
        
        // Wait for expiration
        sleep(2);
        
        $result = SieveScriptCache::getCachedScript($key, $scriptName);
        $this->assertFalse($result);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_invalidateScript() {
        $key = 'test_server';
        $scriptName = 'test.sieve';
        $scriptContent = 'test content';
        
        SieveScriptCache::cacheScript($key, $scriptName, $scriptContent);
        
        $this->assertEquals($scriptContent, SieveScriptCache::getCachedScript($key, $scriptName));
        
        $result = SieveScriptCache::invalidateScript($key, $scriptName);
        $this->assertTrue($result);
        
        $this->assertFalse(SieveScriptCache::getCachedScript($key, $scriptName));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_cacheScriptsList_and_getCachedScriptsList() {
        $key = 'test_server';
        $scripts = ['vacation.sieve', 'spam.sieve', 'forward.sieve'];
        
        $result = SieveScriptCache::cacheScriptsList($key, $scripts);
        $this->assertTrue($result);
        
        $cached = SieveScriptCache::getCachedScriptsList($key);
        $this->assertEquals($scripts, $cached);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_invalidateScriptsList() {
        $key = 'test_server';
        $scripts = ['test1.sieve', 'test2.sieve'];
        
        SieveScriptCache::cacheScriptsList($key, $scripts);
        
        $this->assertEquals($scripts, SieveScriptCache::getCachedScriptsList($key));
        
        $result = SieveScriptCache::invalidateScriptsList($key);
        $this->assertTrue($result);
        
        $this->assertFalse(SieveScriptCache::getCachedScriptsList($key));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_isCached() {
        $key = 'test_server';
        $scriptName = 'test.sieve';
        $scriptContent = 'test content';
        
        $this->assertFalse(SieveScriptCache::isCached($key, $scriptName));
        
        SieveScriptCache::cacheScript($key, $scriptName, $scriptContent);
        
        $this->assertTrue(SieveScriptCache::isCached($key, $scriptName));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_clearScriptCache() {
        $key = 'test_server';
        $scriptName = 'test.sieve';
        $scriptContent = 'test content';
        
        SieveScriptCache::cacheScript($key, $scriptName, $scriptContent);
        
        $result = SieveScriptCache::clearScriptCache($key, $scriptName);
        $this->assertTrue($result);
        
        $this->assertFalse(SieveScriptCache::getCachedScript($key, $scriptName));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_clearAllCache() {
        $key = 'test_server';
        
        SieveScriptCache::cacheScript($key, 'script1.sieve', 'content1');
        SieveScriptCache::cacheScript($key, 'script2.sieve', 'content2');
        SieveScriptCache::cacheScriptsList($key, ['script1.sieve', 'script2.sieve']);
        
        $result = SieveScriptCache::clearAllCache($key);
        $this->assertTrue($result);
        
        $this->assertFalse(SieveScriptCache::getCachedScriptsList($key));
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_operations_without_cache() {
        // Force cache to null using reflection since setCache(null) doesn't work
        $reflection = new ReflectionClass('SieveScriptCache');
        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(null);
        
        // All operations should return false
        $this->assertFalse(SieveScriptCache::cacheScript('key', 'script', 'content'));
        $this->assertFalse(SieveScriptCache::getCachedScript('key', 'script'));
        $this->assertFalse(SieveScriptCache::invalidateScript('key', 'script'));
        $this->assertFalse(SieveScriptCache::cacheScriptsList('key', []));
        $this->assertFalse(SieveScriptCache::getCachedScriptsList('key'));
        $this->assertFalse(SieveScriptCache::invalidateScriptsList('key'));
        $this->assertFalse(SieveScriptCache::clearScriptCache('key', 'script'));
        $this->assertFalse(SieveScriptCache::clearAllCache('key'));
        
        // Restore cache for other tests
        $this->mockCache = new MockCache();
        SieveScriptCache::setCache($this->mockCache);
    }
}
