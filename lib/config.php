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
    protected $source = false;

    /* config data */
    protected $config = array('version' => VERSION);

    /* flag indicating failed decryption */
    public $decrypt_failed = false;

    /* if decryption fails, save the encrypted payload */
    public $encrypted_str = false;

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
     * @param mixed $default value to return if the name is not found
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
     * @param string $data serialized or json encoded string
     * @return mixed array, or false on failure
     */
    public function decode($data) {
        if (!is_string($data) || !trim($data)) {
            return false;
        }
        if (substr($data, 0, 2) === 'a:') {
            return @unserialize($data);
        }
        elseif (substr($data, 0, 1) === '{') {
            return @json_decode($data, true);
        }
        return false;
    }
}

/**
 * File based user settings
 */
class Hm_User_Config_File extends Hm_Config {

    /* config values */
    private $site_config = false;

    /**
     * Load site configuration
     * @param object $config site config
     * @return void
     */
    public function __construct($config) {
        $this->site_config = $config;
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
        $source = $this->get_path($username);
        if (is_readable($source)) {
            $str_data = file_get_contents($source);
            if ($str_data) {
                $data = $this->decode(Hm_Crypt::plaintext($str_data, $key));
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
     * @return void
     */
    public function reload($data) {
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
        $data = Hm_Crypt::ciphertext(json_encode($this->config), $key);
        file_put_contents($destination, $data);
    }
}

/**
 * DB based user settings
 */
class Hm_User_Config_DB extends Hm_Config {

    /* site configuration */
    private $site_config = false;

    /* DB connection handle */
    private $dbh = false;

    /**
     * Load site config
     * @param object $config site config
     * @return void
     */
    public function __construct($config) {
        $this->site_config = $config;
    }

    /**
     * Load the user settings from the DB
     * @param string $username username
     * @param string $key encryption key
     * @return void
     */
    public function load($username, $key) {
        if ($this->connect()) {
            $sql = $this->dbh->prepare("select * from hm_user_settings where username=?");
            if ($sql->execute(array($username))) {
                $data = $sql->fetch();
                if (!$data || !array_key_exists('settings', $data)) {
                    $sql = $this->dbh->prepare("insert into hm_user_settings values(?,?)");
                    if ($sql->execute(array($username, ''))) {
                        Hm_Debug::add(sprintf("created new row in hm_user_settings for %s", $username));
                        $this->config = array();
                    }
                }
                else {
                    $data = $this->decode(Hm_Crypt::plaintext($data['settings'], $key));
                    if (is_array($data)) {
                        $this->config = array_merge($this->config, $data);
                        $this->set_tz();
                    }
                    else {
                        $this->decrypt_failed = true;
                        $this->encrypted_str = $data['settings'];
                    }
                }
            }
        }
    }

    /**
     * Reload from outside input
     * @param array $data new user data
     * @return void
     */
    public function reload($data) {
        $this->config = $data;
        $this->set_tz();
    }

    /**
     * Connect to a configured DB
     * @return bool true on success
     */
    public function connect() {
        $this->dbh = Hm_DB::connect($this->site_config);
        if ($this->dbh) {
            return true;
        }
        return false;
    }

    /**
     * Save user settings to the DB
     * @param string $username username
     * @param string $key encryption key
     * @return void
     */
    public function save($username, $key) {
        $this->shuffle();
        $config = Hm_Crypt::ciphertext(json_encode($this->config), $key);
        if (!$this->connect()) {
            return false;
        }
        $sql = $this->dbh->prepare("update hm_user_settings set settings=? where username=?");
        if ($sql->execute(array($config, $username)) && $sql->rowCount() == 1) {
            Hm_Debug::add(sprintf("Saved user data to DB for %s", $username));
            return true;
        }
        $sql = $this->dbh->prepare("insert into hm_user_settings values(?,?)");
        if ($sql->execute(array($username, $config))) {
            return true;
        }
        return false;
    }
}

/**
 * File based site configuration
 */
class Hm_Site_Config_File extends Hm_Config {

    /**
     * Load data based on source
     * @param string $source source location for site configuration
     * @return void
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
            }
        }
    }

    /**
     * Return a list of modules as an array
     * @return array
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
    switch ($type) {
        case 'DB':
            $user_config = new Hm_User_Config_DB($config);
            Hm_Debug::add("Using DB user configuration");
            break;
        default:
            $user_config = new Hm_User_Config_File($config);
            Hm_Debug::add("Using file based user configuration");
            break;
    }
    return $user_config;
}
