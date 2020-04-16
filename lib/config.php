<?php

/**
 * Configuration objects
 * @package framework
 * @subpackage config
 */

/**
 * Base class for both site and user configuration data management
 */
abstract class Hm_Config {

    /* config source */
    protected $source = '';

    /* config data */
    protected $config = array('version' => VERSION);

    /* flag indicating failed decryption */
    public $decrypt_failed = false;

    /* if decryption fails, save the encrypted payload */
    public $encrypted_str;

    /**
     * This method must be overriden by classes extending this one
     * @param string $source source or identifier to determine the source
     * @param string $key encryption key
     */
    abstract public function load($source, $key);

    /**
     * Return all config values
     * @return array list of config values
     */
    public function dump() {
        return $this->config;
    }

    /**
     * Delete a setting
     * @param string $name config option name
     * @return bool true on success
     */
    public function del($name) {
        if (array_key_exists($name, $this->config)) {
            unset($this->config[$name]);
            return true;
        }
        return false;
    }

    /**
     * Return a versoin number
     * @return float
     */
    public function version() {
        if (array_key_exists('version', $this->config)) {
            return $this->config['version'];
        }
        return .1;
    }

    /**
     * Set a config value
     * @param string $name config value name
     * @param string $value config value
     * @return void
     */
    public function set($name, $value) {
        $this->config[$name] = $value;
    }

    /**
     * Return a config value if it exists
     * @param string $name config value name
     * @param false|string $default value to return if the name is not found
     * @return mixed found value, otherwise $default
     */
    public function get($name, $default=false) {
        return array_key_exists($name, $this->config) ? $this->config[$name] : $default;
    }

    /**
     * Set the timezone
     * @return void
     */
    public function set_tz() {
        date_default_timezone_set($this->get('timezone_setting', 'UTC'));
    }

    /**
     * Shuffle the config value order
     * @return void
     */
    public function shuffle() {
        $new_config = array();
        $keys = array_keys($this->config);
        shuffle($keys);
        foreach ($keys as $key) {
            $new_config[$key] = $this->config[$key];
        }
        $this->config = $new_config;
    }

    /**
     * Decode user settings with json_decode or unserialize depending
     * on the format
     * @param string|false $data serialized or json encoded string
     * @return mixed array, or false on failure
     */
    public function decode($data) {
        if (!is_string($data) || !trim($data)) {
            return false;
        }
        return Hm_Transform::convert($data);
    }

    /**
     * Filter out default auth and SMTP servers so they don't get saved
     * to the permanent user config. These are dynamically reloaded on
     * login
     * @return array of items removed
     */
    public function filter_servers() {
        $removed = array();
        $excluded = array('pop3_servers', 'imap_servers','smtp_servers');
        $no_password = $this->get('no_password_save_setting', false);
        foreach ($this->config as $key => $vals) {
            if (in_array($key, $excluded, true)) {
                foreach ($vals as $index => $server) {
                    if (array_key_exists('default', $server) && $server['default']) {
                        $removed[$key][$index] = $server;
                        unset($this->config[$key][$index]);
                    }
                    elseif (!array_key_exists('server', $server)) {
                        $removed[$key][$index] = $server;
                        unset($this->config[$key][$index]);
                    }
                    else {
                        $this->config[$key][$index]['object'] = false;
                        if ($no_password) {
                            if (!array_key_exists('auth', $server) || $server['auth'] != 'xoauth2') {
                                $removed[$key][$index]['pass'] = $server['pass'];
                                unset($this->config[$key][$index]['pass']);
                            }
                        }
                    }
                }
            }
        }
        return $removed;
    }

    /**
     * Restore server definitions removed before saving
     * @param array $removed server info to restore
     * @return void
     */
    public function restore_servers($removed) {
        foreach ($removed as $key => $vals) {
            foreach ($vals as $index => $server) {
                if (is_array($server)) {
                    $this->config[$key][$index] = $server;
                }
                else {
                    $this->config[$key][$index]['pass'] = $server;
                }
            }
        }
    }
}

/**
 * File based user settings
 */
class Hm_User_Config_File extends Hm_Config {

    /* config values */
    private $site_config;

    /* encrption flag */
    private $crypt;

    /* username */
    private $username;

    /**
     * Load site configuration
     * @param object $config site config
     */
    public function __construct($config) {
        $this->crypt = crypt_state($config);
        $this->site_config = $config;
        $this->config = array_merge($this->config, $config->user_defaults);
    }

    /**
     * Get the filesystem path for a user settings file
     * @param string $username username
     * @return string filepath to the user config file
     */
    public function get_path($username) {
        $path = $this->site_config->get('user_settings_dir', false);
        return sprintf('%s/%s.txt', $path, $username);
    }

    /**
     * Load the settings for a user
     * @param string $username username
     * @param string $key key to decrypt the user data
     * @return void
     */
    public function load($username, $key) {
        $this->username = $username;
        $source = $this->get_path($username);
        if (is_readable($source)) {
            $str_data = file_get_contents($source);
            if ($str_data) {
                if (!$this->crypt) {
                    $data = $this->decode($str_data);
                }
                else {
                    $data = $this->decode(Hm_Crypt::plaintext($str_data, $key));
                }
                if (is_array($data)) {
                    $this->config = array_merge($this->config, $data);
                    $this->set_tz();
                }
                else {
                    $this->decrypt_failed = true;
                    $this->encrypted_str = $str_data;
                }
            }
        }
    }

    /**
     * Reload from outside input
     * @param array $data new user data
     * @param string $username
     * @return void
     */
    public function reload($data, $username=false) {
        $this->username = $username;
        $this->config = $data;
        $this->set_tz();
    }

    /**
     * Save user settings to a file
     * @param string $username username
     * @param string $key encryption key
     * @return void
     */
    public function save($username, $key) {
        $this->shuffle();
        $destination = $this->get_path($username);
        $removed = $this->filter_servers();
        if (!$this->crypt) {
            $data = json_encode($this->config);
        }
        else {
            $data = Hm_Crypt::ciphertext(json_encode($this->config), $key);
        }
        file_put_contents($destination, $data);
        $this->restore_servers($removed);
    }

    /**
     * Set a config value
     * @param string $name config value name
     * @param string $value config value
     * @return void
     */
    public function set($name, $value) {
        $this->config[$name] = $value;
        if (!$this->crypt) {
            $this->save($this->username, false);
        }
    }
}

/**
 * DB based user settings
 */
class Hm_User_Config_DB extends Hm_Config {

    /* site configuration */
    private $site_config;

    /* DB connection handle */
    private $dbh;

    /* encrption class */
    private $crypt;

    /* username */
    private $username;

    /**
     * Load site config
     * @param object $config site config
     */
    public function __construct($config) {
        $this->crypt = crypt_state($config);
        $this->site_config = $config;
        $this->config = array_merge($this->config, $config->user_defaults);
    }

    /**
     * @param string $username
     * @return boolean
     */
    private function new_settings($username) {
        $res = Hm_DB::execute($this->dbh, 'insert into hm_user_settings values(?,?)', array($username, ''));
        Hm_Debug::add(sprintf("created new row in hm_user_settings for %s", $username));
        $this->config = array();
        return $res ? true : false;
    }

    /**
     * @param array $data
     * @param string $key
     * @return boolean
     */
    private function decrypt_settings($data, $key) {
        if (!$this->crypt) {
            $data = $this->decode($data['settings']);
        }
        else {
            $data = $this->decode(Hm_Crypt::plaintext($data['settings'], $key));
        }
        if (is_array($data)) {
            $this->config = array_merge($this->config, $data);
            $this->set_tz();
            return true;
        }
        else {
            $this->decrypt_failed = true;
            return false;
        }
    }

    /**
     * Load the user settings from the DB
     * @param string $username username
     * @param string $key encryption key
     * @return boolean
     */
    public function load($username, $key) {
        $this->username = $username;
        $this->connect();
        $data = Hm_DB::execute($this->dbh, 'select * from hm_user_settings where username=?', array($username));
        if (!$data || !array_key_exists('settings', $data)) {
            return $this->new_settings($username);
        }
        return $this->decrypt_settings($data, $key);
    }

    /**
     * Reload from outside input
     * @param array $data new user data
     * @param string $username
     * @return void
     */
    public function reload($data, $username=false) {
        $this->username = $username;
        $this->config = $data;
        $this->set_tz();
    }

    /**
     * Connect to a configured DB
     * @return bool true on success
     */
    public function connect() {
        return ($this->dbh = Hm_DB::connect($this->site_config)) ? true : false;
    }

    /**
     * Save user settings to the DB
     * @param string $username username
     * @param string $key encryption key
     * @return integer|boolean|array
     */
    public function save($username, $key) {
        $this->shuffle();
        $removed = $this->filter_servers();
        if (!$this->crypt) {
            $config = json_encode($this->config);
        }
        else {
            $config = Hm_Crypt::ciphertext(json_encode($this->config), $key);
        }
        $this->connect();
        if (Hm_DB::execute($this->dbh, 'update hm_user_settings set settings=? where username=?', array($config, $username))) {
            Hm_Debug::add(sprintf("Saved user data to DB for %s", $username));
            $res = true;
        }
        else {
            $res = Hm_DB::execute($this->dbh, 'insert into hm_user_settings values(?,?)', array($username, $config));
        }
        $this->restore_servers($removed);
        return $res;
    }

    /**
     * Set a config value
     * @param string $name config value name
     * @param string $value config value
     * @return void
     */
    public function set($name, $value) {
        $this->config[$name] = $value;
        if (!$this->crypt) {
            $this->save($this->username, false);
        }
    }
}

/**
 * File based site configuration
 */
class Hm_Site_Config_File extends Hm_Config {

    public $user_defaults = array();

    /**
     * Load data based on source
     * @param string $source source location for site configuration
     */
    public function __construct($source) {
        $this->load($source, false);
    }

    /**
     * Load site data from a file
     * @param string $source file path to the site configuration
     * @param string $key encryption key (unsued in this class)
     * @return void
     */
    public function load($source, $key) {
        if (is_readable($source)) {
            $data = $this->decode(file_get_contents($source));
            if ($data) {
                $this->config = array_merge($this->config, $data);
                $this->get_user_defaults();
            }
        }
    }

    /*
     * Determine default values for users without any settings
     * @return void
     */
    private function get_user_defaults() {
        foreach ($this->config as $name => $val) {
            if (substr($name, 0, 15) == 'default_setting') {
                $this->user_defaults[substr($name, 16).'_setting'] = $val;
            }
        }
    }

    /**
     * Return a list of modules as an array
     * @return array|false
     */
    public function get_modules() {
        $mods = $this->get('modules');
        if (is_string($mods)) {
            return explode(',', $mods);
        }
        return $mods;
    }
}

/**
 * Load a user config object
 * @param object $config site configuration
 * @return object
 */
function load_user_config_object($config) {
    $type = $config->get('user_config_type', 'file');
    if (strstr($type, ':')) {
        list($type, $class) = explode(':', $type);
    }
    switch ($type) {
        case 'DB':
            $user_config = new Hm_User_Config_DB($config);
            Hm_Debug::add("Using DB user configuration");
            break;
        case 'custom':
            if (class_exists($class)) {
                $user_config = new $class($config);
                Hm_Debug::add("Using custom user configuration: $class");
                break;
            } else {
                Hm_Debug::add("User configuration class does not exist: $class");
            }
        default:
            $user_config = new Hm_User_Config_File($config);
            Hm_Debug::add("Using file based user configuration");
            break;
    }
    return $user_config;
}

/**
 * Determine encryption for user settings
 * @param object $config site configuration
 * @return boolean
 */
function crypt_state($config) {
    if ($config->get('single_server_mode') &&
        in_array($config->get('auth_type'), array('IMAP', 'POP3'), true)) {
        return false;
    }
    return true;
}
