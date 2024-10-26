<?php

/**
 * Redis wrapper
 * @package framework
 * @subpackage db
 */
class Hm_Redis {

    /** Redis connection handler */
    static private $redis;

    /** Required Redis configuration parameters */
    static private $required_config = ['redis_host', 'redis_port'];

    /** Redis config */
    static private $config;

    /**
     * Load Redis configuration from the site config
     * @param object $site_config site config
     * @return void
     */
    static private function parse_config($site_config) {
        self::$config = [
            'redis_host' => $site_config->get('redis_host', false),
            'redis_port' => $site_config->get('redis_port', false),
            'redis_password' => $site_config->get('redis_password', null), // Optional
            'redis_username' => $site_config->get('redis_username', null), // Optional for Redis 6+
            'redis_prefix' => $site_config->get('redis_prefix', ''), // Optional prefix for keys
        ];

        foreach (self::$required_config as $v) {
            if (!self::$config[$v]) {
                Hm_Debug::add(sprintf('Missing configuration setting for %s', $v));
            }
        }
    }

    /**
     * Connect to a Redis server
     * @param object $site_config site settings
     * @return object|false Redis connection on success
     */
    static public function connect($site_config) {
        self::parse_config($site_config);

        if (self::$redis) {
            return self::$redis;
        }

        try {
            self::$redis = new Redis();
            self::$redis->connect(self::$config['redis_host'], self::$config['redis_port']);

            // Authenticate with password if provided
            if (self::$config['redis_password']) {
                self::$redis->auth(self::$config['redis_password']);
            }

            // Authenticate with username if provided (for Redis 6+)
            if (self::$config['redis_username']) {
                self::$redis->auth(self::$config['redis_username'], self::$config['redis_password']);
            }

            // Optionally, set a key prefix
            if (self::$config['redis_prefix']) {
                self::$redis->setOption(Redis::OPT_PREFIX, self::$config['redis_prefix']);
            }

            Hm_Debug::add(sprintf('Connected to Redis at %s:%s', self::$config['redis_host'], self::$config['redis_port']));
            return self::$redis;
        } catch (Exception $oops) {
            Hm_Debug::add($oops->getMessage());
            Hm_Msgs::add('ERRUnable to connect to Redis. Please check your configuration settings and try again.');
            return false;
        }
    }

    /**
     * Set a value in Redis
     * @param string $key
     * @param mixed $value
     * @param int|null $expire
     * @return bool
     */
    static public function set($key, $value, $expire = null) {
        if (!self::$redis) {
            return false;
        }
        return self::$redis->set($key, $value, $expire);
    }

    /**
     * Get a value from Redis
     * @param string $key
     * @return mixed
     */
    static public function get($key) {
        if (!self::$redis) {
            return false;
        }
        return self::$redis->get($key);
    }

    /**
     * Delete a key from Redis
     * @param string $key
     * @return bool
     */
    static public function delete($key) {
        if (!self::$redis) {
            return false;
        }
        return self::$redis->delete($key);
    }

    /**
     * Check if a key exists in Redis
     * @param string $key
     * @return bool
     */
    static public function exists($key) {
        if (!self::$redis) {
            return false;
        }
        return self::$redis->exists($key);
    }

    /**
     * Get the Redis connection
     * @return Redis|null
     */
    static public function getConnection() {
        return self::$redis;
    }
}
