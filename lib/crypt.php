<?php

/**
 * Encryption
 * @package framework
 * @subpackage crypt
 */

if (!defined('DEBUG_MODE')) { die(); }

/* block mode */
define('BLOCK_MODE', MCRYPT_MODE_CBC);

/* cipher */
define('CIPHER', MCRYPT_RIJNDAEL_128);

/* rand source */
define('RAND_SOURCE', MCRYPT_RAND);

/**
 * class Hm_Crypt
 * Class to make encryption easier
 */
class Hm_Crypt {

    /* mode */
    static private $mode = BLOCK_MODE;

    /* cipher */
    static private $cipher = CIPHER;

    /* rand source */
    static private $r_source = RAND_SOURCE;

    /**
     * Convert ciphertext to plaintext
     *
     * @param string $string ciphertext to decrypt
     * @param string $key encryption key
     *
     * @return string decrypted text
     */
    public static function plaintext($string, $key) {
        $key = substr(md5($key), 0, mcrypt_get_key_size(self::$cipher, self::$mode));
        $string = base64_decode($string);
        $iv_size = self::iv_size();
        $iv_dec = substr($string, 0, $iv_size);
        $string = substr($string, $iv_size);
        return mcrypt_decrypt(self::$cipher, $key, $string, self::$mode, $iv_dec);
    }

    /**
     * Convert plaintext into ciphertext
     *
     * @param string $string plaintext to encrypt
     * @param string $key encryption key
     *
     * @return string encrypted text
     */
    public static function ciphertext($string, $key) {
        $key = substr(md5($key), 0, mcrypt_get_key_size(self::$cipher, self::$mode));
        $iv_size = self::iv_size();
        $iv = mcrypt_create_iv($iv_size, self::$r_source);
        return base64_encode($iv.mcrypt_encrypt(self::$cipher, $key, $string, self::$mode, $iv));
    }

    /**
     * Calculate iv size
     *
     * @return int "Initialization Vector" size for the selected cipher/mode
     */
    public static function iv_size() {
        return mcrypt_get_iv_size(self::$cipher, self::$mode);
    }

    /**
     * Return a unique-enough-key for session cookie ids
     *
     * @return string
     */
    public static function unique_id($size=128) {
        return base64_encode(openssl_random_pseudo_bytes($size));
    }
}

/**
 * class Hm_Nonce
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
     * @param object $session session interface
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
     * @param string $key key to validate
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
     * @param object $session session interface
     *
     * @return void
     */
    public static function save($session) {
        $session->set('nonce_list', self::$nonce_list);
    }

    /**
     * Validate a nonce
     *
     * @param string $nonce value to check
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
