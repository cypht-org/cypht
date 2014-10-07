<?php

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Base class for both site and user configuration data management
 */
abstract class Hm_Config {

    /* config source */
    protected $source = false;

    /* config data */
    protected $config = array();

    /**
     * This method must be overriden by classes extending this one
     */
    abstract public function load($source, $key);

    /**
     * Return all config values
     *
     * @return array list of config values
     */
    public function dump() {
        return $this->config;
    }

    /**
     * Set a config value
     *
     * @param $name string config value name
     * @param $value string config value
     *
     * @return void
     */
    public function set($name, $value) {
        $this->config[$name] = $value;
    }

    /**
     * Return a config value if it exists
     *
     * @param $name string config value name
     * @param $default mixed value to return if the name is not found
     *
     * @return mixed found value, otherwise $default
     */
    public function get($name, $default=false) {
        return array_key_exists($name, $this->config) ? $this->config[$name] : $default;
    }

    /**
     * Set the timezone
     *
     * @return void
     */
    protected function set_tz() {
        date_default_timezone_set($this->get('timezone_setting', 'UTC'));
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
     *
     * @param $config object site config
     *
     * @return void
     */
    public function __construct($config) {
        $this->site_config = $config;
    }

    /**
     * Get the filesystem path for a user settings file
     *
     * @param $username string username
     * 
     * @return string filepath to the user config file
     */
    private function get_path($username) {
        $path = $this->site_config->get('user_settings_dir', false);
        return sprintf('%s/%s.txt', $path, $username);
    }

    /**
     * Load the settings for a user
     *
     * @param $username string username
     * @param $key string key to decrypt the user data
     *
     * @return void
     */
    public function load($username, $key) {
        $source = $this->get_path($username);
        if (is_readable($source)) {
            $str_data = file_get_contents($source);
            if ($str_data) {
                $data = @unserialize(Hm_Crypt::plaintext($str_data, $key));
                if (is_array($data)) {
                    $this->config = array_merge($this->config, $data);
                    $this->set_tz();
                }
            }
        }
    }

    /**
     * Reload from outside input
     *
     * @param $data array new user data
     *
     * @return void
     */
    public function reload($data) {
        $this->config = $data;
        $this->set_tz();
    }

    /**
     * Save user settings to a file
     *
     * @param $username string username
     * @param $key string encryption key
     *
     * @return void
     */
    public function save($username, $key) {
        $destination = $this->get_path($username);
        $data = Hm_Crypt::ciphertext(serialize($this->config), $key);
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
     *
     * @param $config object site config
     *
     * @return void
     */
    public function __construct($config) {
        $this->site_config = $config;
    }

    /**
     * Load the user settings from the DB
     *
     * @param $username string username
     * @param $key string encryption key
     *
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
                    $data = @unserialize(Hm_Crypt::plaintext($data['settings'], $key));
                    if (is_array($data)) {
                        $this->config = array_merge($this->config, $data);
                        $this->set_tz();
                    }
                }
            }
        }
    }

    /**
     * Reload from outside input
     *
     * @param $data array new user data
     *
     * @return void
     */
    public function reload($data) {
        $this->config = $data;
        $this->set_tz();
    }

    /**
     * Connect to a configured DB
     *
     * @return bool true on success
     */
    protected function connect() {
        $this->dbh = Hm_DB::connect($this->site_config);
        if ($this->dbh) {
            return true;
        }
        return false;
    }

    /**
     * Save user settings to the DB
     *
     * @param $username string username
     * @param $key string encryption key
     *
     * @return void
     */
    public function save($username, $key) {
        $config = Hm_Crypt::ciphertext(serialize($this->config), $key);
        if ($this->connect()) {
            $sql = $this->dbh->prepare("update hm_user_settings set settings=? where username=?");
            if ($sql->execute(array($config, $username))) {
                Hm_Debug::add(sprintf("Saved user data to DB for %s", $username));
            }
        }
    }
}

/**
 * File based site configuration
 * */
class Hm_Site_Config_File extends Hm_Config {

    /**
     * Load data based on source
     *
     * @param $source string source location for user data
     *
     * @return void
     */
    public function __construct($source) {
        $this->load($source, false);
    }

    /**
     * Load user data from a file
     *
     * @param $source string file path to the user settings
     * @param $key string encryption key
     *
     * @return void
     */
    public function load($source, $key) {
        if (is_readable($source)) {
            $data = unserialize(file_get_contents($source));
            if ($data) {
                $this->config = array_merge($this->config, $data);
            }
        }
    }
}

?>
