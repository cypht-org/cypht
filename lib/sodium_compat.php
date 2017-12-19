<?php

/**
 * Support for libsodium PECL package, and the upcoming PHP 7.2 built in extension
 * The 2 versions of libsodium differ in the following ways:
 *
 * PECL uses a Sodium namespace, the PHP version does not
 * PECL exposes the extension as "libsodium", the PHP verions is "sodium"
 * PHP prefixes functions and constants with "sodium_"
 *
 * @package framework
 * @subpackage crypt
 */

class Hm_Sodium_PECL {

    public static function crypto_auth_verify($hmac, $crypt_string, $crypt_key) {
        return \Sodium\crypto_auth_verify($hmac, $crypt_string, $crypt_key);
    }

    public static function crypto_secretbox_open($crypt_string, $salt, $crypt_key) {
        return \Sodium\crypto_secretbox_open($crypt_string, $salt, $crypt_key);
    }

    public static function crypto_secretbox($string, $salt, $key) {
        return \Sodium\crypto_secretbox($string, $salt, $key);
    }

    public static function crypto_auth($ciphertext, $key) {
        return \Sodium\crypto_auth($ciphertext, $key);
    }

    public static function crypto_pwhash_str($password) {
        return \Sodium\crypto_pwhash_str($password, \Sodium\CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            \Sodium\CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
    }

    public static function crypto_pwhash_str_verify($hash, $password) {
        return \Sodium\crypto_pwhash_str_verify($hash, $password);
    }

    public static function randombytes_buf() {
        return \Sodium\randombytes_buf(\Sodium\CRYPTO_SECRETBOX_NONCEBYTES);
    }
}

/**
 * @package framework
 * @subpackage crypt
 */
class Hm_Sodium_PHP {
    public static function crypto_auth_verify($hmac, $crypt_string, $crypt_key) {
        return sodium_crypto_auth_verify($hmac, $crypt_string, $crypt_key);
    }

    public static function crypto_secretbox_open($crypt_string, $salt, $crypt_key) {
        return sodium_crypto_secretbox_open($crypt_string, $salt, $crypt_key);
    }

    public static function crypto_secretbox($string, $salt, $key) {
        return sodium_crypto_secretbox($string, $salt, $key);
    }

    public static function crypto_auth($ciphertext, $key) {
        return sodium_crypto_auth($ciphertext, $key);
    }

    public static function crypto_pwhash_str($password) {
        return sodium_crypto_pwhash_str($password, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
    }

    public static function crypto_pwhash_str_verify($hash, $password) {
        return sodium_crypto_pwhash_str_verify($hash, $password);
    }

    public static function randombytes_buf() {
        return random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    }
}
