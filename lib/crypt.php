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
     * @param string $string ciphertext to decrypt
     * @param string $key encryption key
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
     * @param string $string plaintext to encrypt
     * @param string $key encryption key
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
     * @return int "Initialization Vector" size for the selected cipher/mode
     */
    public static function iv_size() {
        return mcrypt_get_iv_size(self::$cipher, self::$mode);
    }

    /**
     * Return a unique-enough-key for session cookie ids
     * @return string
     */
    public static function unique_id($size=128) {
        return base64_encode(openssl_random_pseudo_bytes($size));
    }
}

/**
 * Manage request keys for modules
 */
class Hm_Request_Key {

    /* site key */
    private static $site_hash = false;

    /**
     * Load the request key
     * @param object $session session interface
     * @param object $request request object
     * @param bool $just_logged_in true if the session was created on this request
     * @return void
     */
    public static function load($session, $request, $just_logged_in) {
        if ($session->is_active() && !$just_logged_in) {
            $user = $session->get('username', false);
        }
        else {
            $user = '';
        }
        self::$site_hash = $session->build_fingerprint($request, $user.SITE_ID);
    }

    /**
     * Return the request key
     * @return string request key
     */
    public static function generate() {
        return self::$site_hash;
    }

    /**
     * Validate a request key
     * @param string $key value to check
     * @return bool true on success
     */
    public static function validate($key) {
        return $key === self::$site_hash;
    }
}
?>
