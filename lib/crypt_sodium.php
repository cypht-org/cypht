<?php

/**
 * Encryption with the libsodium extension
 *
 * This extends our standard encryption and will override it if libsodium is
 * present. Checking a password and converting cipher text to clear text fall
 * back to the standard Hm_Crypt library if they fail.  This is to ensure
 * backwards compatibility.
 *
 * @package framework
 * @subpackage crypt
 */

class Hm_Crypt extends Hm_Crypt_Base {

    /**
     * Convert ciphertext to plaintext
     * @param string $string ciphertext to decrypt
     * @param string $key encryption key
     * @return string|false decrypted text
     */
    public static function plaintext($string, $key) {
        if (!LIBSODIUM) {
            return parent::plaintext($string, $key);
        }
        $res = false;
        $raw_string = base64_decode($string);
        if (!$raw_string || strlen($raw_string) < 60) {
            return false;
        }
        list($salt, $crypt_key) = self::keygen($key, substr($raw_string, 0, 24));
        $hmac = substr($raw_string, 24, 32);
        $crypt_string = substr($raw_string, 56);

        if (Hm_Sodium_Compat::crypto_auth_verify($hmac, $crypt_string, $crypt_key)) {
            $res = Hm_Sodium_Compat::crypto_secretbox_open($crypt_string, $salt, $crypt_key);
        }
        if ($res === false) {
            return parent::plaintext($string, $key);
        }
        return $res;
    }

    /**
     * Convert plaintext into ciphertext
     * @param string $string plaintext to encrypt
     * @param string $key encryption key
     * @return string encrypted text
     */
    public static function ciphertext($string, $key) {
        if (!LIBSODIUM) {
            return parent::ciphertext($string, $key);
        }
        list($salt, $key) = self::keygen($key);
        $ciphertext = Hm_Sodium_Compat::crypto_secretbox($string, $salt, $key);
        $mac = Hm_Sodium_Compat::crypto_auth($ciphertext, $key);
        return base64_encode($salt.$mac.$ciphertext);
    }

    /**
     * Hash a password using libsodium. Args not defined here are for backwards
     * compat usage with our built in crypto
     * @param string $password password to hash
     * @return string
     */
    public static function hash_password($password, $salt=false, $count=false, $algo='sha512', $type='php') {
        if (!LIBSODIUM) {
            return parent::hash_password($password, $salt, $count, $algo, $type);
        }
        return Hm_Sodium_Compat::crypto_pwhash_str($password);
    }

    /**
     * Check a password against it's stored hash using libsodium. If that
     * fails, try our built in crypto otherwise updating to libsodium breaks
     * everything
     * @param string $password clear text password
     * @param string $hash hashed password
     * @return bool
     */
    public static function check_password($password, $hash) {
        if (!LIBSODIUM) {
            return parent::check_password($password, $hash);
        }
        if (!Hm_Sodium_Compat::crypto_pwhash_str_verify($hash, $password)) {
            return parent::check_password($password, $hash);
        }
        return true;
    }

    /**
     * stretch (or shrink) a key for libsodium
     * @param string $key the key to stretch/shrink
     * @param string $salt a salt to use, or create one if needed
     * @return string[]
     */
    protected static function keygen($key, $salt=false) {
        if ($salt === false) {
            $salt = Hm_Sodium_Compat::randombytes_buf();
        }
        return parent::keygen($key, $salt);
    }

}
