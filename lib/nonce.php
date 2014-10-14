<?php

/**
 * Manage nonces easily for modules
 */

class Hm_Nonce {

    /* valid nonce list */
    public static $nonce_list = array();

    /* site key */
    private static $site_hash = false;

    /* nonce for the current page */
    private static $current_nonce = false;

    /* max number to allow as valid */
    private static $max = 5;

    /**
     * Load a saved list from the session
     *
     * @param $session object session interface
     * 
     * @return void
     */
    public static function load($session, $config, $request) {
        self::$nonce_list = $session->get('nonce_list', array());
        self::$site_hash = $session->build_fingerprint($request, $config->get('site_key', ''));
    }

    /**
     * Return the site key, used for logged out page nonce
     *
     * @return string site key
     */
    public static function site_key() {
        return self::$site_hash;
    }

    /**
     * Validate the site key
     *
     * @param $key string key to validate
     *
     * @return bool
     */
    public static function validate_site_key($key) {
        return $key == self::$site_hash;
    }

    /**
     * Generate a new nonce, and trim the list if need be
     *
     * @return string new random string
     */
    public static function generate() {
        if (!self::$current_nonce) {
            self::$current_nonce = Hm_Crypt::unique_id();
        }
        self::$nonce_list[] = self::$current_nonce;
        self::trim_list(); 
        return self::$current_nonce;
    }

    /**
     * Keep the list tidy
     *
     * @return void
     */
    private static function trim_list() {
        if (count(self::$nonce_list) == self::$max) {
            array_shift(self::$nonce_list);
        }
    }

    /**
     * Save the list into the session
     *
     * @param $session object session interface
     *
     * @return void
     */
    public static function save($session) {
        $session->set('nonce_list', self::$nonce_list);
    }

    /**
     * Validate a nonce
     *
     * @param $nonce string value to check
     *
     * @return bool true on success
     */
    public static function validate($nonce) {
        foreach (self::$nonce_list as $index => $value) {
            if ($value == $nonce) {
                return true;
            }
        }
        return false;
    }
}

?>
