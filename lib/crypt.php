<?php

/**
 * Encryption
 * @package framework
 * @subpackage crypt
 */

/**
 * Manage request keys for modules
 */
class Hm_Request_Key {

    /* site key */
    private static $site_hash = '';

    /**
     * Load the request key
     * @param object $session session interface
     * @param object $request request object
     * @param bool $just_logged_in true if the session was created on this request
     * @return void
     */
    public static function load($session, $request, $just_logged_in) {
        $user = '';
        $key = '';
        if ($session->is_active()) {
            if (!$just_logged_in) {
                $user = $session->get('username', '');
                $key = $session->get('request_key', '');
            }
            else {
                $session->set('request_key', Hm_Crypt::unique_id());
            }
        }
        $site_id = '';
        if (defined('SITE_ID')) {
            $site_id = SITE_ID;
        }
        self::$site_hash = $session->build_fingerprint($request->server, $key.$user.$site_id);
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

class Hm_Crypt_Base {

    static protected $method = 'aes-256-cbc';
    static protected $hmac = 'sha512';
    static protected $password_rounds = 86000;
    static protected $encryption_rounds = 100;
    static protected $hmac_rounds = 101;

    /**
     * Convert ciphertext to plaintext
     * @param string $string ciphertext to decrypt
     * @param string $key encryption key
     * @return string|false decrypted text
     */
    public static function plaintext($string, $key) {
        $string = base64_decode($string);

        /* bail if the crypt text is invalid */
        if (!$string || strlen($string) <= 200) {
            return false;
        }

        /* get the payload and salt */
        $crypt_string = substr($string, 192);
        $salt = substr($string, 0, 128);

        /* check the signature. Temporarily allow the same key for hmac validation, eventually remove the $encryption_rounds
         * check and require the hmac_rounds check only! */
        if (!self::check_hmac($crypt_string, substr($string, 128, 64), $salt, $key, self::$hmac_rounds) &&
            !self::check_hmac($crypt_string, substr($string, 128, 64), $salt, $key, self::$encryption_rounds)) {
            Hm_Debug::add('HMAC verification failed');
            return false;
        }

        /* generate remaining keys */
        $iv = self::pbkdf2($key, $salt, 16, self::$encryption_rounds, self::$hmac);
        $crypt_key = self::pbkdf2($key, $salt, 32, self::$encryption_rounds, self::$hmac);

        /* return the decrpted text */
        return openssl_decrypt($crypt_string, self::$method, $crypt_key, OPENSSL_RAW_DATA, $iv);

    }

    /**
     * Check hmac signature
     * @param string $crypt_string payload to check
     * @param string $hmac signature to check
     * @param string $salt from generate_salt()
     * @param string $key supplied key for the encryption
     * @param integer $rounds iterations
     * @return boolean
     */
    public static function check_hmac($crypt_string, $hmac, $salt, $key, $rounds) {
        $hmac_key = self::pbkdf2($key, $salt, 32, $rounds, self::$hmac);

        /* make sure the crypt text has not been tampered with */
        return self::hash_compare($hmac, hash_hmac(self::$hmac, $crypt_string, $hmac_key, true));
    }

    /**
     * Convert plaintext into ciphertext
     * @param string $string plaintext to encrypt
     * @param string $key encryption key
     * @return string encrypted text
     */
    public static function ciphertext($string, $key) {
        /* generate a strong salt */
        $salt = self::generate_salt();

        /* build required keys */
        $iv = self::pbkdf2($key, $salt, 16, self::$encryption_rounds, self::$hmac);
        $crypt_key = self::pbkdf2($key, $salt, 32, self::$encryption_rounds, self::$hmac);
        $hmac_key = self::pbkdf2($key, $salt, 32, self::$hmac_rounds, self::$hmac);

        /* encrypt the string */
        $crypt_string = openssl_encrypt($string, self::$method, $crypt_key, OPENSSL_RAW_DATA, $iv);

        /* build a hash of the crypted text */
        $hmac = hash_hmac(self::$hmac, $crypt_string, $hmac_key, true);

        /* return the salt, hash, and crypt text */
        return base64_encode($salt.$hmac.$crypt_string);
    }

    /**
     * Generate a strong random salt (hopefully)
     * @return string
     */
    public static function generate_salt() {
        /* generate random bytes */
        return self::random(128);
    }

    /**
     * Compare password hashes
     *
     * @param string $a hash
     * @param string $b hash
     * @return bool
    */
    private static function hash_equals($a, $b) {
        $res = 0;
        $len = strlen($a);
        for ($i = 0; $i < $len; $i++) {
            $res |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $res === 0;
    }

    /**
     * Compare password hashes with hash_equals is available, otherwise use
     * timing attack safe comparison
     *
     * @param string $a hash
     * @param string $b hash
     * @return bool
     */ 
    public static function hash_compare($a, $b) {
        if (!is_string($a) || !is_string($b) || strlen($a) !== strlen($b)) {
            return false;
        }
        /* requires PHP >= 5.6 */
        if (Hm_Functions::function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        return self::hash_equals($a, $b);
    }

    /**
     * Key derivation wth pbkdf2: http://en.wikipedia.org/wiki/PBKDF2
     * @param string $key payload
     * @param string $salt random string from generate_salt
     * @return string[]
     */
    protected static function keygen($key, $salt) {
        return array($salt, self::pbkdf2($key, $salt, 32, self::$encryption_rounds, self::$hmac));
    }
    /**
     * Key derivation wth pbkdf2: http://en.wikipedia.org/wiki/PBKDF2
     * @param string $key payload
     * @param string $salt random string from generate_salt
     * @param integer $length result length
     * @param integer $count iterations
     * @param string $algo hash algorithm to use
     * @return string
     */
    public static function pbkdf2($key, $salt, $length, $count, $algo) {
        /* requires PHP >= 5.5 */
        if (Hm_Functions::function_exists('openssl_pbkdf2')) {
            return openssl_pbkdf2($key, $salt, $length, $count, $algo);
        }

        /* manual version */
        $size = strlen(hash($algo, '', true));
        $len = ceil($length / $size);
        $result = '';
        for ($i = 1; $i <= $len; $i++) {
            $tmp = hash_hmac($algo, $salt . pack('N', $i), $key, true);
            $res = $tmp;
            for ($j = 1; $j < $count; $j++) {
                 $tmp  = hash_hmac($algo, $tmp, $key, true);
                 $res ^= $tmp;
            }
            $result .= $res;
        }
        return substr($result, 0, $length);
    }

    /**
     * Hash a password using PBKDF2 or PHP password_hash if availble
     * @param string $password password to hash
     * @param string $salt salt to use, if false generate a new one
     * @param int $count interations for PBKDF2
     * @param string $algo PBKDF2 algo, defaults to sha512
     * @param string $type Can be either pbkdf2 or php
     * @return string
     */
    public static function hash_password($password, $salt=false, $count=false, $algo='sha512', $type='php') {
        if (function_exists('password_hash') && $type === 'php') {
            return password_hash($password,  PASSWORD_DEFAULT);
        }
        if ($salt === false) {
            $salt = self::generate_salt();
        }
        if ($count === false) {
            $count = self::$password_rounds;
        }
        return sprintf("%s:%s:%s:%s", $algo, $count, base64_encode($salt), base64_encode(
            self::pbkdf2($password, $salt, 32, $count, $algo)));
    }

    /**
     * Check a password against it's stored hash
     * @param string $password clear text password
     * @param string $hash hashed password
     * @return bool
     */
    public static function check_password($password, $hash) {
        $type = 'php';
        if (substr($hash, 0, 6) === 'sha512') {
            $type = 'pbkdf2';
        }
        if (function_exists('password_verify') && $type === 'php') {
            return password_verify($password, $hash);
        }
        if (count(explode(':', $hash)) == 4) {
            list($algo, $count, $salt,,) = explode(':', $hash);
            return self::hash_compare(self::hash_password($password, base64_decode($salt), $count, $algo, $type), $hash);
        }
        return false;
    }

    /**
     * Return a unique-enough-key for session cookie ids
     * @param int $size length of the result
     * @return string
     */
    public static function unique_id($size=128) {
        return base64_encode(openssl_random_pseudo_bytes($size));
    }

    /**
     * Generate a random string
     * @param int $size
     * @return string
     */
    public static function random($size=128) {
        try {
            return Hm_Functions::random_bytes($size);
        }
        catch (Exception $e) {
            Hm_Functions::cease('No reliable random byte source found');
        }
    }
}
