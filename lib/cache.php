<?php


/*
 * TODO:
 * - add flush on logout?
 * - scrutinizer fixes
 * - redis sessions
 */

/**
 * Cache structures
 * @package framework
 * @subpackage cache
 */

/**
 * Helper struct to provide data sources the don't track messages read or flagged state
 * (like POP3 or RSS) with an alternative.
 * @package framework
 * @subpackage cache
 */
trait Hm_Uid_Cache {

    /* UID list */
    private static $read = array();
    private static $unread = array();

    /* Load UIDs from an outside source
     * @param array $data list of uids
     * @return void
     */
    public static function load($data) {
        if (!is_array($data) || count($data) != 2) {
            return;
        }
        if (count($data[0]) > 0) {
            self::update_count($data, 'read', 0);
        }
        if (count($data[1]) > 0) {
            self::update_count($data, 'unread', 1);
        }
    }

    /**
     * @param array $data uids to merge
     * @param string $type uid type (read or unread)
     * @param integer $pos position in the $data array
     * @return void
     */
    private static function update_count($data, $type, $pos) {
        self::$$type = array_combine($data[$pos], array_fill(0, count($data[$pos]), 0));
    }

    /**
     * Determine if a UID has been unread
     * @param string $uid UID to search for
     * @return bool true if te UID exists
     */
    public static function is_unread($uid) {
        return array_key_exists($uid, self::$unread);
    }

    /**
     * Determine if a UID has been read
     * @param string $uid UID to search for
     * @return bool true if te UID exists
     */
    public static function is_read($uid) {
        return array_key_exists($uid, self::$read);
    }

    /**
     * Return all the UIDs
     * @return array list of known UIDs
     */
    public static function dump() {
        return array(array_keys(self::$read), array_keys(self::$unread));
    }

    /**
     * Add a UID to the unread list 
     * @param string $uid uid to add
     */
    public static function unread($uid) {
        self::$unread[$uid] = 0;
        if (array_key_exists($uid, self::$read)) {
            unset(self::$read[$uid]);
        }
    }

    /**
     * Add a UID to the read list 
     * @param string $uid uid to add
     */
    public static function read($uid) {
        self::$read[$uid] = 0;
        if (array_key_exists($uid, self::$unread)) {
            unset(self::$unread[$uid]);
        }
    }
}

/**
 * Shared utils for Redis and Memcached
 * @package framework
 * @subpackage cache
 */
trait Hm_Cache_Base {

    public $supported;
    private $enabled;
    private $server;
    private $config;
    private $port;
    private $cache_con;

    /**
     * @return boolean
     */
    abstract protected function connect();

    /*
     * @return boolean
     */
    public function is_active() {
        if (!$this->enabled) {
            return false;
        }
        elseif (!$this->configured()) {
            return false;
        }
        elseif (!$this->cache_con) {
            return $this->connect();
        }
        return true;
    }

    /**
     * @param string $key cache key to delete
     */
    public function del($key) {
        if (!$this->is_active()) {
            return false;
        }
        return $this->cache_con->delete($key);
    }

    /**
     * @param string $key key to set
     * @param string|string $val value to set
     * @param integer $lifetime lifetime of the cache entry
     * @param string $crypt_key encryption key
     * @return boolean
     */
    public function set($key, $val, $lifetime=600, $crypt_key='') {
        if (!$this->is_active()) {
            return false;
        }
        return $this->cache_con->set($key, $this->prep_in($val, $crypt_key), $lifetime);
    }

    /**
     * @param string $key name of value to fetch
     * @param string $crypt_key encryption key
     * @return false|array|string
     */
    public function get($key, $crypt_key='') {
        if (!$this->is_active()) {
            return false;
        }
        return $this->prep_out($this->cache_con->get($key), $crypt_key);
    }

    /**
     * @param array|string $data data to prep
     * @param string $crypt_key encryption key
     * @return string|array
     */
    private function prep_in($data, $crypt_key) {
        if ($crypt_key) {
            return Hm_Crypt::ciphertext(Hm_transform::stringify($data), $crypt_key);
        }
        return $data;
    }

    /**
     * @param array $data data to prep
     * @param string $crypt_key encryption key
     * @return false|array|string
     */
    private function prep_out($data, $crypt_key) {
        if ($crypt_key && is_string($data) && trim($data)) {
            return Hm_transform::unstringify(Hm_Crypt::plaintext($data, $crypt_key), 'base64_decode', true);
        }
        return $data;
    }

    /**
     * @return boolean
     */
    private function configured() {
        if (!$this->server || !$this->port) {
            Hm_Debug::add(sprintf('%s enabled but no server or port found', $this->type));
            return false;
        }
        if (!$this->supported) {
            Hm_Debug::add(sprintf('%s enabled but not supported by PHP', $this->type));
            return false;
        }
        return true;
    }
}

/**
 * Redis cache
 * @package framework
 * @subpackage cache
 */
class Hm_Redis {

    use Hm_Cache_Base;
    private $type = 'Redis';
    private $db_index;

    /**
     * @param Hm_Config $config site config object
     */
    public function __construct($config) {
        $this->server = $config->get('redis_server', false);
        $this->port = $config->get('redis_port', false);
        $this->enabled = $config->get('enable_redis', false);
        $this->db_index = $config->get('redis_index', 0);
        $this->socket = $config->get('redis_socket', '');
        $this->supported = Hm_Functions::class_exists('Redis');
        $this->config = $config;
    }

    /**
     * @return boolean
     */
    private function connect() {
        $this->cache_con = Hm_Functions::redis();
        try {
            if ($this->socket) {
                $con = $this->cache_con->connect($this->socket);
            }
            else {
                $con = $this->cache_con->connect($this->server, $this->port);
            }
            if ($con) {
                $this->auth();
                $this->cache_con->select($this->db_index);
                return true;
            }
            else {
                $this->cache_con = false;
                return false;
            }
        }
        catch (Exception $oops) {
            Hm_Debug::add('Redis connect failed');
            $this->cache_con = false;
            return false;
        }
    }

    /**
     * @return void
     */
    private function auth() {
        if ($this->config->get('redis_pass')) {
            $this->cache_con->auth($this->config->get('redis_pass'));
        }
    }
    
    /**
     * @param string $key cache key to delete
     */
    public function del($key) {
        if (!$this->is_active()) {
            return false;
        }
        return $this->cache_con->del($key);
    }


    /**
     * @return boolean
     */
    public function close() {
        if (!$this->is_active()) {
            return false;
        }
        return $this->cache_con->close();
    }
}

/**
 * Memcached cache
 * @package framework
 * @subpackage cache
 */
class Hm_Memcached {

    use Hm_Cache_Base;
    private $type = 'Memcached';

    /**
     * @param Hm_Config $config site config object
     */
    public function __construct($config) {
        $this->server = $config->get('memcached_server', false);
        $this->port = $config->get('memcached_port', false);
        $this->enabled = $config->get('enable_memcached', false);
        $this->supported = Hm_Functions::class_exists('Memcached');
        $this->config = $config;
    }

    /**
     * @return void
     */
    private function auth() {
        if ($this->config->get('memcached_auth')) {
            $this->cache_con->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
            $this->cache_con->setSaslAuthData($this->config->get('memcached_user'),
                $this->config->get('memcached_pass'));
        }
    }

    /*
     * @return boolean
     */
    private function connect() {
        $this->cache_con = Hm_Functions::memcached();
        $this->auth();
        if (!$this->cache_con->addServer($this->server, $this->port)) {
            Hm_Debug::add('Memcached addServer failed');
            $this->cache_con = false;
            return false;
        }
        return true;
    }

    /**
     * @return mixed
     */
    public function last_err() {
        if (!$this->is_active()) {
            return false;
        }
        return $this->cache_con->getResultCode();
    }

    /**
     * @return boolean
     */
    public function close() {
        if (!$this->is_active()) {
            return false;
        }
        return $this->cache_con->quit();
    }
}

/**
 * @package framework
 * @subpackage cache
 */
class Hm_Noop_Cache {

    public function del($key) {
        return true;
    }
    public function set($key, $val, $lifetime, $crypt_key) {
        return false;
    }
}

/**
 * Generic cache
 * @package framework
 * @subpackage cache
 */
class Hm_Cache {

    private $backend;
    private $session;
    public $type;

    /**
     * @param Hm_Config $config site config object
     * @param object $session session object
     * @return void
     */
    public function __construct($config, $session) {
        $this->session = $session;
        if (!$this->check_redis($config) && !$this->check_memcache($config)) {
            $this->check_session($config);
        }
        Hm_Debug::add(sprintf('CACHE backend using: %s', $this->type));
    }

    /**
     * @param Hm_Config $config site config object
     * @return void
     */
    private function check_session($config) {
        $this->type = 'noop';
        $this->backend = new Hm_Noop_Cache();
        if ($config->get('allow_session_cache')) {
            $this->type = 'session';
        }
    }

    /**
     * @param Hm_Config $config site config object
     * @return boolean
     */
    private function check_redis($config) {
        if ($config->get('enable_redis', false)) {
            $backend = new Hm_Redis($config);
            if ($backend->is_active()) {
                $this->type = 'redis';
                $this->backend = $backend;
                return true;
            }
        }
        return false;
    }

    /**
     * @param Hm_Config $config site config object
     * @return boolean
     */
    private function check_memcache($config) {
        if ($config->get('enable_memcached', false)) {
            $backend = new Hm_Memcached($config);
            if ($backend->is_active()) {
                $this->type = 'memcache';
                $this->backend = $backend;
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $key key name
     * @param string $msg_type log message
     * @return void
     */
    private function log($key, $msg_type) {
        switch ($msg_type) {
        case 'save':
            Hm_Debug::add(sprintf('CACHE: saving "%s" using %s', $key, $this->type));
            break;
        case 'hit':
            Hm_Debug::add(sprintf('CACHE: hit for "%s" using %s', $key, $this->type));
            break;
        case 'miss':
            Hm_Debug::add(sprintf('CACHE: miss for "%s" using %s', $key, $this->type));
            break;
        case 'del':
            Hm_Debug::add(sprintf('CACHE: deleting "%s" using %s', $key, $this->type));
            break;
        }
    }

    /**
     * @param string $key name of value to cache
     * @param mixed $val value to cache
     * @param integer $lifetime how long to cache (if applicable for the backend)
     * @param boolean $session store in the session instead of the enabled cache
     * @return boolean
     */
    public function set($key, $val, $lifetime=600, $session=false) {
        if ($session || $this->type == 'session') {
            return $this->session_set($key, $val, false);
        }
        return $this->generic_set($key, $val, $lifetime);
    }

    /**
     * @param string $key name of value to fetch
     * @param mixed $default value to return if not found
     * @param boolean $session fetch from the session instead of the enabled cache
     * @return mixed
     */
    public function get($key, $default=false, $session=false) {
        if ($session || $this->type == 'session') {
            return $this->session_get($key, $default);
        }
        return $this->{$this->type.'_get'}($key, $default);
    }

    /**
     * @param string $key name to delete
     * @param boolean $session fetch from the session instead of the enabled cache
     * @return boolean
     */
    public function del($key, $session=false) {
        if ($session || $this->type == 'session') {
            return $this->session_del($key);
        }
        return $this->generic_del($key);
    }

    /**
     * @param string $key name of value to fetch
     * @param mixed $default value to return if not found
     * @return mixed
     */
    private function redis_get($key, $default) {
        $res = $this->backend->get($this->key_hash($key), $this->session->enc_key);
        if (!$res) {
            $this->log($key, 'miss');
            return $default;
        }
        $this->log($key, 'hit');
        return $res;
    }

    /**
     * @param string $key name of value to fetch
     * @param mixed $default value to return if not found
     * @return mixed
     */
    private function memcache_get($key, $default) {
        $res = $this->backend->get($this->key_hash($key), $this->session->enc_key);
        if (!$res && $this->backend->last_err() == Memcached::RES_NOTFOUND) {
            $this->log($key, 'miss');
            return $default;
        }
        $this->log($key, 'hit');
        return $res;
    }

    /*
     * @param string $key name of value to cache
     * @param mixed $val value to cache
     * @param integer $lifetime how long to cache (if applicable for the backend)
     * @return boolean
     */
    private function session_set($key, $val, $lifetime) {
        $this->log($key, 'save');
        $this->session->set($this->key_hash($key), $val);
        return true;
    }

    /**
     * @param string $key name of value to fetch
     * @param mixed $default value to return if not found
     * @return mixed
     */
    private function session_get($key, $default) {
        $res = $this->session->get($this->key_hash($key), $default);
        if ($res === $default) {
            $this->log($key, 'miss');
            return $default;
        }
        $this->log($key, 'hit');
        return $res;
    }

    /**
     * @param string $key name to delete
     * @return boolean
     */
    private function session_del($key) {
        $this->log($key, 'del');
        return $this->session->del($this->key_hash($key));
    }

    /**
     * @param string $key name of value to fetch
     * @param mixed $default value to return if not found
     * @return mixed
     */
    private function noop_get($key, $default) {
        return $default;
    }

    /*
     * @param string $key key to make the hash unique
     * @return string
     */
    private function key_hash($key) {
        return sprintf('hm_cache_%s', hash('sha256', (sprintf('%s%s%s%s', $key, SITE_ID,
            $this->session->get('fingerprint'), $this->session->get('username')))));
    }

    /**
     * @param string $key name to delete
     * @return boolean
     */
    private function generic_del($key) {
        $this->log($key, 'del');
        return $this->backend->del($this->key_hash($key));
    }

    /**
     * @param string $key name of value to cache
     * @param mixed $val value to cache
     * @param integer $lifetime how long to cache (if applicable for the backend)
     * @return boolean
     */
    private function generic_set($key, $val, $lifetime) {
        $this->log($key, 'save');
        return $this->backend->set($this->key_hash($key), $val, $lifetime, $this->session->enc_key);
    }
}
