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
    private static $timeout = 300;

    private static $scriptCache = [];
    /**
     * Cache TTL in seconds
     */
    private static $scriptCacheTTL = 300;

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
     * Get a script by name for a given connection key with caching
     */
    public static function getScript($key, string $scriptName)
    {
        // Check cache first
        if (isset(self::$scriptCache[$key][$scriptName])) {
            $cacheEntry = self::$scriptCache[$key][$scriptName];
            if ((time() - $cacheEntry['time']) < self::$scriptCacheTTL) {
                return $cacheEntry['data'];
            }
        }
        
        // Cache miss or expired — fetch from server
        $client = self::get($key);
        exit(var_dump($client));
        $script = $client->getScript($scriptName);

        // Cache the script data with current timestamp
        self::$scriptCache[$key][$scriptName] = [
            'data' => $script,
            'time' => time()
        ];

        return $script;
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
        $client = new ClientWithTimeout($serverConfig['host'], $serverConfig['port']);
        $client->connect(
            $serverConfig['username'],
            $serverConfig['password'],
            $serverConfig['secure'] ?? true,
            "",
            $serverConfig['authType'] ?? "PLAIN"
        );
        // $client->listScripts();
        self::getScript($serverConfig['id'],'blocked_senders');
        // $sock = $client->getSocket();
        // if (is_resource($sock)) {
        //     stream_set_timeout($sock, 10);
        // }
        return $client;
    }
}

class ClientWithTimeout extends Client
{
    // public function getSocket()
    // {
    //     return $this->sock;
    // }
}
