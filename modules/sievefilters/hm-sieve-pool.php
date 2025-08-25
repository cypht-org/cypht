<?php
ini_set('memory_limit', '2048M');

use PhpSieveManager\ManageSieve\Client;
class SieveConnectionPool
{
    private static $config = [];
    private static $connections = [];
    private static $lastConnectionTimes = [];
    /**
     * Default timeout for connections in seconds.
     */
    private static $timeout = 600;

    /**
     * Cache TTL in seconds
     */
    private static $scriptCacheTTL = 600;

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
        if(!self::$cache) {
            self::$cache = $cacheInstance;
        }
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
     * Get a Sieve connection by its key (server identifier)
     */
    public static function get($key)
    {
        if (!isset(self::$config[$key])) {
            throw new Exception("No configuration found for '$key'");
        }

        if (!isset(self::$connections[$key]) || !self::isAlive($key)) {
            self::$connections[$key] = self::connectServer(self::$config[$key]);
            self::$lastConnectionTimes[$key] = time();
        }

        return self::$connections[$key];
    }

    /**
     * Get a script by name for a given connection key with persistent caching
     */
    public static function getScript($key, string $scriptName)
    {
        if (!self::$cache) {
            throw new Exception("Cache instance not set. Call SieveConnectionPool::setCache() first.");
        }

        $cacheKey = "sieve_script_{$key}_{$scriptName}";

        // Try to fetch from persistent cache
        $cached = self::$cache->get($cacheKey, false, true);
        
        if ($cached && isset($cached['time']) && (time() - $cached['time']) < self::$scriptCacheTTL) {
            return $cached['data'];
        }
        
        // Cache miss — fetch from server
        $client = self::get($key);
        $script = $client->getScript($scriptName);
        
        // Save into persistent cache
        self::$cache->set($cacheKey, [
            'data' => $script,
            'time' => time()
        ], self::$scriptCacheTTL, true);

        return $script;
    }

    /**
     * Optional: clear cached script
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
     * Check if the connection is still alive based on timeout
     */
    private static function isAlive($key)
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
}
