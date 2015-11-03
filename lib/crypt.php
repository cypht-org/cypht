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
        self::$site_hash = $session->build_fingerprint($request->server, $user.SITE_ID);
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

class Hm_Crypt {

    static public $strong = true;
    static private $method = 'aes-256-cbc';
    static private $hmac = 'sha512';
    static private $password_rounds = 86000;
    static private $encryption_rounds = 100;

    /**
     * Convert ciphertext to plaintext
     * @param string $string ciphertext to decrypt
     * @param string $key encryption key
     * @return string decrypted text
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

        /* check the signature */
        if (!self::check_hmac($crypt_string, substr($string, 128, 64), $salt, $key)) {
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
     * @param string salt from generate_salt()
     * @param string key supplied key for the encryption
     */
    public static function check_hmac($crypt_string, $hmac, $salt, $key) {
        $hmac_key = self::pbkdf2($key, $salt, 32, self::$encryption_rounds, self::$hmac);

        /* make sure the crypt text has not been tampered with */
        if ($hmac !== hash_hmac(self::$hmac, $crypt_string, $hmac_key, true)) {
            return false;
        }
        return true;
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
        self::random_bytes_check();

        /* build required keys */
        $iv = self::pbkdf2($key, $salt, 16, self::$encryption_rounds, self::$hmac);
        $crypt_key = self::pbkdf2($key, $salt, 32, self::$encryption_rounds, self::$hmac);
        $hmac_key = self::pbkdf2($key, $salt, 32, self::$encryption_rounds, self::$hmac);

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
     * Output a debug message if openssl_random_pseudo_bytes is borked
     * @return bool
     */
    public static function random_bytes_check() {
        if (!self::$strong) {
            Hm_Debug::add('WARNING: openssl_random_pseudo_bytes not cryptographically strong');
        }
        return self::$strong;
    }

    /**
     * Compare password hashes
     *
     * @param string $a hash
     * @param string $b hash
     * @return bool
     */ 
    public static function hash_compare($a, $b) {
        /* requires PHP >= 5.6 */
        if (!is_string($a) || !is_string($b) || strlen($a) !== strlen($b)) {
            return false;
        }
        if (Hm_Functions::function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        return $a === $b;
    }

    /**
     * Key derivation wth pbkdf2: http://en.wikipedia.org/wiki/PBKDF2
     * @param string $key payload
     * @param string $salt random string from generate_salt
     * @param string $length result length
     * @param string $count iterations
     * @param string $algo hash algorithm to use
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
     * Hash a password using PBKDF2
     * @param string $password password to hash
     * @param string $salt salt to use, if false generate a new one
     * @param int $count interations for PBKDF2
     * @param string $algo PBKDF2 algo, defaults to sha512
     * @return string
     */
    public static function hash_password($password, $salt=false, $count=false, $algo='sha512') {
        if (!$salt) {
            $salt = self::generate_salt();
            self::random_bytes_check();
        }
        if (!$count) {
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
        if (count(explode(':', $hash)) == 4) {
            list($algo, $count, $salt, $nothing) = explode(':', $hash);
            return self::hash_compare(self::hash_password($password, base64_decode($salt), $count, $algo), $hash);
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
        if (function_exists('mcrypt_create_iv') && defined('MCRYPT_DEV_URANDOM')) {
            $res = mcrypt_create_iv($size, MCRYPT_DEV_URANDOM);
            self::$strong = true;
        }
        else {
            $res = openssl_random_pseudo_bytes(128, $strong);
            self::$strong = $strong;
        }
        return $res;
    }
}
