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
        ];

        $result = self::$cache->set($cacheKey, $cacheData, self::$scriptCacheTTL, true);
        
        return $result;
    }

    /**
     * Invalidate cache for a specific script
     */
    public static function invalidateScript($key, string $scriptName)
    {
        return self::clearScriptCache($key, $scriptName);
    }

    /**
     * Invalidate all scripts cache for a connection key (only the list, individual scripts expire via TTL)
     */
    public static function invalidateAllScripts($key)
    {
        return self::invalidateScriptsList($key);
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
        return self::$cache->set($cacheKey, $scripts, self::$scriptCacheTTL);
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
        $cached = self::$cache->get($cacheKey, null);
        return $cached !== null ? $cached : false;
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
     * Clear cached script — must pass $session=true to match where cacheScript stores it
     */
    public static function clearScriptCache($key, string $scriptName)
    {
        if (!self::$cache) {
            return false;
        }
        $cacheKey = "sieve_script_{$key}_{$scriptName}";
        return self::$cache->del($cacheKey, true);
    }

    /**
     * Cache server extensions in session — extensions change only on server upgrade
     */
    public static function cacheExtensions($key, array $extensions)
    {
        if (!self::$cache) {
            return false;
        }
        return self::$cache->set("sieve_extensions_{$key}", $extensions, self::$scriptCacheTTL, true);
    }

    /**
     * Get cached extensions, false on miss
     */
    public static function getCachedExtensions($key)
    {
        if (!self::$cache) {
            return false;
        }
        $cached = self::$cache->get("sieve_extensions_{$key}", null, true);
        return $cached !== null ? $cached : false;
    }

    /**
     * Clear all cached scripts for a key
     */
    public static function clearAllCache($key)
    {
        if (!self::$cache) {
            return false;
        }
        self::invalidateAllScripts($key);
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

    public static function getConfig()
    {
        return self::$config;
    }

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

    public static function hasConnection($key): bool
    {
        return isset(self::$connections[$key]) && self::isConnectionAlive($key);
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
        $cachedScript = SieveScriptCache::getCachedScript($key, $scriptName);
        if ($cachedScript !== false) {
            return $cachedScript;
        }
        
        $client = SieveConnectionManager::getConnection($key);
        $script = $client->getScript($scriptName);
        
        SieveScriptCache::cacheScript($key, $scriptName, $script);

        return $script;
    }

    /**
     * List all scripts for a given connection key with caching
     */
    public static function listScripts($key)
    {
        $cached = SieveScriptCache::getCachedScriptsList($key);
        if ($cached !== false) {
            return $cached;
        }
        
        $client = SieveConnectionManager::getConnection($key);
        $scripts = $client->listScripts();
        
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
        
        SieveScriptCache::clearScriptCache($key, $scriptName);
        
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
        
        SieveScriptCache::clearScriptCache($key, $scriptName);
        
        SieveScriptCache::invalidateScriptsList($key);
        
        return $result;
    }

    /**
     * Close connection
     */
    public static function closeConnection($key)
    {
        if (SieveConnectionManager::hasConnection($key)) {
            try {
                $client = SieveConnectionManager::getConnection($key);
                $client->close();
            } catch (Exception $e) {
                // ignore close errors
            }
        }
        SieveConnectionManager::closeConnection($key);
    }

    /**
     * Rename a script on the server
     * Also clears cache for old and new names
     */
    public static function renameScript($key, string $oldName, string $newName)
    {
        $connection = self::getConnection($key);
        $result = $connection->renameScript($oldName, $newName);

        self::clearScriptCache($key, $oldName);
        self::clearScriptCache($key, $newName);

        return $result;
    }

    /**
     * Get server capabilities
     */
    public static function getCapabilities($key)
    {
        $connection = self::getConnection($key);
        return $connection->getCapabilities();
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

    public static function hasAccounts()
    {
        return !empty(SieveConnectionManager::getConfig());
    }

    /**
     * Get extensions from cache or fetch once and cache them
     */
    public static function getExtensions($key)
    {
        $cached = SieveScriptCache::getCachedExtensions($key);
        if ($cached !== false) {
            return $cached;
        }
        $client = SieveConnectionManager::getConnection($key);
        $extensions = $client->getExtensions();
        SieveScriptCache::cacheExtensions($key, $extensions);
        return $extensions;
    }
}
