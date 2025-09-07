<?php

use PhpSieveManager\ManageSieve\Client;

/**
 * Pure cache logic - no connection management
 */
class SieveScriptCache
{
    /**
     * Cache TTL in seconds (increased for session backend)
     */
    private static $scriptCacheTTL = 3600; // 1 hour

    /**
     * Cache instance (injected or global)
     */
    private static $cache = null;

    /**
     * Debug: Track cache access count per request
     */
    private static $accessCount = [];

    private function __construct() {}

    /**
     * Optionally inject a global cache instance (Hm_Cache)
     */
    public static function setCache($cacheInstance)
    {
        // Validate cache instance
        if (!is_object($cacheInstance)) {
            return;
        }
        
        if (!self::$cache || get_class($cacheInstance) !== get_class(self::$cache)) {
            self::$cache = $cacheInstance;
        }
    }

    /**
     * Set cache TTL
     */
    public static function setCacheTTL(int $ttl)
    {
        self::$scriptCacheTTL = $ttl;
    }

    /**
     * Get cached script data
     */
    public static function getCachedScript($key, string $scriptName)
    {
        if (!self::$cache) {
            return false;
        }

        $cacheKey = "sieve_script_{$key}_{$scriptName}";
        
        // Track access count
        if (!isset(self::$accessCount[$cacheKey])) {
            self::$accessCount[$cacheKey] = 0;
        }
        self::$accessCount[$cacheKey]++;
        
        $cached = self::$cache->get($cacheKey, false, true);
        
        if ($cached && isset($cached['time']) && (time() - $cached['time']) < self::$scriptCacheTTL) {
            return $cached['data'];
        }
        
        return false;
    }

    /**
     * Cache script data
     */
    public static function cacheScript($key, string $scriptName, $scriptData)
    {
        if (!self::$cache) {
            return false;
        }

        $cacheKey = "sieve_script_{$key}_{$scriptName}";
        $cacheData = [
            'data' => $scriptData,
            'time' => time(),
            'microtime' => microtime(true)
        ];
        
        $result = self::$cache->set($cacheKey, $cacheData, self::$scriptCacheTTL, true);
        
        return $result;
    }

    /**
     * Invalidate cache for a specific script
     */
    public static function invalidateScript($key, string $scriptName)
    {
        if (!self::$cache) {
            return false;
        }

        $cacheKey = "sieve_script_{$key}_{$scriptName}";
        return self::$cache->del($cacheKey);
    }

    /**
     * Invalidate all scripts cache for a connection key
     */
    public static function invalidateAllScripts($key)
    {
        if (!self::$cache) {
            return false;
        }

        // We need to track script names to invalidate, 
        // for now we'll invalidate the list cache and scripts will be re-fetched
        $listCacheKey = "sieve_scripts_list_{$key}";
        return self::$cache->del($listCacheKey);
    }

    /**
     * Cache scripts list for a connection
     */
    public static function cacheScriptsList($key, array $scripts)
    {
        if (!self::$cache) {
            return false;
        }

        $cacheKey = "sieve_scripts_list_{$key}";
        return self::$cache->set($cacheKey, $scripts, 300); // 5 minutes TTL
    }

    /**
     * Get cached scripts list for a connection
     */
    public static function getCachedScriptsList($key)
    {
        if (!self::$cache) {
            return false;
        }

        $cacheKey = "sieve_scripts_list_{$key}";
        return self::$cache->get($cacheKey);
    }

    /**
     * Invalidate scripts list cache for a connection
     */
    public static function invalidateScriptsList($key)
    {
        if (!self::$cache) {
            return false;
        }

        $cacheKey = "sieve_scripts_list_{$key}";
        return self::$cache->del($cacheKey);
    }

    /**
     * Check if script is cached and fresh
     */
    public static function isCached($key, string $scriptName)
    {
        return self::getCachedScript($key, $scriptName) !== false;
    }

    /**
     * Clear cached script
     */
    public static function clearScriptCache($key, string $scriptName)
    {
        if (!self::$cache) {
            return false;
        }
        $cacheKey = "sieve_script_{$key}_{$scriptName}";
        return self::$cache->del($cacheKey);
    }

    /**
     * Clear all cached scripts for a key
     */
    public static function clearAllCache($key)
    {
        if (!self::$cache) {
            return false;
        }
        // Implementation depends on cache backend
        // This is a simplified version
        return true;
    }
}

/**
 * Connection management only - no cache logic
 */
class SieveConnectionManager
{
    private static $config = [];
    private static $connections = [];
    private static $lastConnectionTimes = [];
    
    /**
     * Default timeout for connections in seconds.
     */
    private static $timeout = 600;

    private function __construct() {}

    /**
     * Set the configuration for all servers
     */
    public static function setConfig(array $servers)
    {
        if (empty($servers)) {
            throw new Exception("Configuration cannot be empty.");
        }
        
        foreach ($servers as $key => $server) {
            if (!isset($server['host'], $server['port'], $server['username'], $server['password'])) {
                throw new Exception("Each server configuration must include 'host', 'port', 'username', and 'password'.");
            }
            if (!is_string($server['host']) || !is_int($server['port']) || !is_string($server['username']) || !is_string($server['password'])) {
                throw new Exception("Invalid types in server configuration for '$key'.");
            }
            
            if (isset(self::$config[$key])) {
                self::$config[$key] = array_merge(self::$config[$key], $server);
            } else {
                self::$config[$key] = $server;
            }
        }
    }

    /**
     * Set connection timeout
     */
    public static function setTimeout(int $timeout)
    {
        self::$timeout = $timeout;
    }

    /**
     * Get a Sieve connection by its key (server identifier)
     */
    public static function getConnection($key)
    {
        if (!isset(self::$config[$key])) {
            throw new Exception("No configuration found for '$key'");
        }

        if (!isset(self::$connections[$key]) || !self::isConnectionAlive($key)) {
            self::$connections[$key] = self::connectServer(self::$config[$key]);
            self::$lastConnectionTimes[$key] = time();
        }

        return self::$connections[$key];
    }

    /**
     * Check if the connection is still alive based on timeout
     */
    private static function isConnectionAlive($key)
    {
        return isset(self::$lastConnectionTimes[$key]) &&
               (time() - self::$lastConnectionTimes[$key]) < self::$timeout;
    }

    /**
     * Establish the Sieve connection
     */
    private static function connectServer(array $serverConfig)
    {
        $client = new Client($serverConfig['host'], $serverConfig['port']);
        $client->connect(
            $serverConfig['username'],
            $serverConfig['password'],
            $serverConfig['secure'] ?? true,
            "",
            $serverConfig['authType'] ?? "PLAIN"
        );
        return $client;
    }

    /**
     * Close connection for a specific key
     */
    public static function closeConnection($key)
    {
        if (isset(self::$connections[$key])) {
            unset(self::$connections[$key]);
            unset(self::$lastConnectionTimes[$key]);
        }
    }

    /**
     * Close all connections
     */
    public static function closeAllConnections()
    {
        self::$connections = [];
        self::$lastConnectionTimes = [];
    }
}

/**
 * Service orchestrator - combines cache and connection management
 * This provides backward compatibility and clean API
 */
class SieveService
{
    /**
     * Initialize the service with cache and config
     */
    public static function init($cacheInstance, array $serverConfigs)
    {
        SieveScriptCache::setCache($cacheInstance);
        SieveConnectionManager::setConfig($serverConfigs);
    }

    /**
     * Get a script by name for a given connection key with caching
     */
    public static function getScript($key, string $scriptName)
    {
        // Try cache first
        $cachedScript = SieveScriptCache::getCachedScript($key, $scriptName);
        if ($cachedScript !== false) {
            return $cachedScript;
        }
        
        // Cache miss — fetch from server
        $client = SieveConnectionManager::getConnection($key);
        $script = $client->getScript($scriptName);
        
        // Cache the result
        SieveScriptCache::cacheScript($key, $scriptName, $script);

        return $script;
    }

    /**
     * List all scripts for a given connection key with caching
     */
    public static function listScripts($key)
    {
        // Try cache first
        $cached = SieveScriptCache::getCachedScriptsList($key);
        if ($cached !== false) {
            return $cached;
        }
        
        // Get from server and cache
        $client = SieveConnectionManager::getConnection($key);
        $scripts = $client->listScripts();
        
        // Cache the list
        if ($scripts !== false) {
            SieveScriptCache::cacheScriptsList($key, $scripts);
        }
        
        return $scripts;
    }

    /**
     * Put/upload a script
     */
    public static function putScript($key, string $scriptName, string $scriptContent)
    {
        $client = SieveConnectionManager::getConnection($key);
        $result = $client->putScript($scriptName, $scriptContent);
        
        // Clear cache for this script since it's been updated
        SieveScriptCache::clearScriptCache($key, $scriptName);
        
        // Invalidate scripts list cache since a new script might have been added
        SieveScriptCache::invalidateScriptsList($key);
        
        return $result;
    }

    /**
     * Activate a script
     */
    public static function activateScript($key, string $scriptName)
    {
        $client = SieveConnectionManager::getConnection($key);
        return $client->activateScript($scriptName);
    }

    /**
     * Remove a script
     */
    public static function removeScripts($key, string $scriptName)
    {
        $client = SieveConnectionManager::getConnection($key);
        $result = $client->removeScripts($scriptName);
        
        // Clear cache for this script since it's been removed
        SieveScriptCache::clearScriptCache($key, $scriptName);
        
        // Invalidate scripts list cache since a script has been removed
        SieveScriptCache::invalidateScriptsList($key);
        
        return $result;
    }

    /**
     * Close connection
     */
    public static function closeConnection($key)
    {
        $client = SieveConnectionManager::getConnection($key);
        return $client->close();
    }

    /**
     * Rename a script on the server
     */
    public static function renameScript($key, string $oldName, string $newName)
    {
        try {
            $connection = self::getConnection($key);
            $result = $connection->renameScript($oldName, $newName);
            
            // Invalidate cache for both old and new names
            self::clearScriptCache($key, $oldName);
            self::clearScriptCache($key, $newName);
            
            return $result;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get server capabilities
     */
    public static function getCapabilities($key)
    {
        try {
            $connection = self::getConnection($key);
            $result = $connection->getCapabilities();
            
            return $result;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get connection (for operations that don't need caching)
     */
    public static function getConnection($key)
    {
        return SieveConnectionManager::getConnection($key);
    }

    /**
     * Clear cached script
     */
    public static function clearScriptCache($key, string $scriptName)
    {
        return SieveScriptCache::clearScriptCache($key, $scriptName);
    }

    /**
     * Configuration methods
     */
    public static function setCacheTTL(int $ttl)
    {
        SieveScriptCache::setCacheTTL($ttl);
    }

    public static function setConnectionTimeout(int $timeout)
    {
        SieveConnectionManager::setTimeout($timeout);
    }
}
