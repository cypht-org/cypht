<?php

if (!defined('DEBUG_MODE')) { die(); }

/* switch to control encryption. Please don't disable */
define('MCRYPT_DATA', true);

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
     *
     * @param $string string ciphertext to decrypt
     * @param $key string encryption key
     *
     * @return string decrypted text
     */
    public static function plaintext($string, $key) {
        if (!MCRYPT_DATA) {
            return $string;
        }
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
     * @param $string string plaintext to encrypt
     * @param $key string encryption key
     *
     * @return string encrypted text
     */
    public static function ciphertext($string, $key) {
        if (!MCRYPT_DATA) {
            return $string;
        }
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
    public static function unique_id() {
        return base64_encode(openssl_random_pseudo_bytes(128));
    }
}

?>
