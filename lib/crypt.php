<?php

/**
 * Encryption
 * @package framework
 * @subpackage crypt
 */

if (!defined('DEBUG_MODE')) { die(); }

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

class Hm_Crypt {

    /* mode */
    static private $mode = MCRYPT_MODE_CBC;

    /* cipher */
    static private $cipher = MCRYPT_RIJNDAEL_128;

    /* rand source */
    static private $r_source = MCRYPT_RAND;

    /* hmac algo */
    static private $hmac = 'sha512';

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

        /* split up into salt, hmac, and crypt text */
        $hmac = substr($string, 128, 64);
        $crypt_string = substr($string, 192);
        $salt = substr($string, 0, 128);

        /* generate the hmac key */
        $hmac_key = self::generate_hmac_key($salt, $key);

        /* make sure the crypt text has not been tampered with */
        if ($hmac !== hash_hmac(self::$hmac, $crypt_string, $hmac_key, true)) {
            return false;
        }

        /* generate remaining keys */
        $iv = self::generate_iv($salt, $key);
        $crypt_key = self::generate_crypt_key($salt, $key);

        /* return the decrpted text */
        return self::unpad(mcrypt_decrypt(self::$cipher, $crypt_key, $crypt_string, self::$mode, $iv));
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
        $iv = self::generate_iv($salt, $key);
        $crypt_key = self::generate_crypt_key($salt, $key);
        $hmac_key = self::generate_hmac_key($salt, $key);

        /* encrypt the string */
        $crypt_string = mcrypt_encrypt(self::$cipher, $crypt_key, self::pad($string), self::$mode, $iv);

        /* build a hash of the crypted text */
        $hmac = hash_hmac(self::$hmac, $crypt_string, $hmac_key, true);

        /* return the salt, hash, and crypt text */
        return base64_encode($salt.$hmac.$crypt_string);
    }

    /**
     * Generate a strong random salt
     * @return string
     */
    public static function generate_salt() {
        return mcrypt_create_iv(128, MCRYPT_DEV_URANDOM);
    }

    /**
     * Generate an ecncryption key from a user key and a salt
     * @param string salt from generate_salt()
     * @param string key supplied key for the encryption
     * @return string
     */
    public static function generate_crypt_key($salt, $key) {
        return self::pbkdf2(self::$hmac, $key, $salt, 100, 32);
    }

    /**
     * Generate an hmac key from a user key and a salt
     * @param string salt from generate_salt()
     * @param string key supplied key for the encryption
     * @return string
     */
    public static function generate_hmac_key($salt, $key) {
        return self::pbkdf2(self::$hmac, $key, $salt, 100, 32);
    }

    /**
     * Generate an iv from a user key and a salt
     * @param string salt from generate_salt()
     * @param string key supplied key for the encryption
     * @return string
     */
    public static function generate_iv($salt, $key) {
        return self::pbkdf2(self::$hmac, $key, $salt, 100, 16);
    }

    /**
     * time constant string comparison for passwords
     *
     * @param string $a hash
     * @param string $b hash
     * @return bool
     */ 
    public static function hash_compare($a, $b) {
        $diff = strlen($a) ^ strlen($b);
        for($i = 0; $i < strlen($a) && $i < strlen($b); $i++) {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $diff === 0;
    }

    /**
     * Pad a string before encryption
     *
     * @param string $data data to pad
     * @return string
     */
    public static function pad($data) {
        $len = mcrypt_get_block_size(self::$cipher, self::$mode);
        $diff = $len - strlen($data);
        if ($diff < 0) {
            return $data;
        }
        if ($diff % $len == 0) {
            $diff = $len;
        }
        return $data.str_repeat(chr($diff), $diff);
    }

    /**
     * Hash a password using PBKDF2
     * @param string $password password to hash
     * @param string $salt salt to use, if false generate a new one
     * @param int $count interations, defaults to 86000
     * @param string $algo pbkdf2 algo, defaults to sha512
     * @return string
     */
    public static function hash_password($password, $salt=false, $count=86000, $algo='sha512') {
        if (!$salt) {
            $salt = base64_encode(self::generate_salt());
        }
        return sprintf("%s:%s:%s:%s", $algo, $count, $salt, base64_encode(
            self::pbkdf2($algo, $password, $salt, $count, 32)));
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
            return self::hash_compare(self::hash_password($password, $salt, $count, $algo), $hash);
        }
        return false;
    }

    /**
     * remove padding from a decrypted string
     * @param string $data string to unpad
     * @return string
     */
    public static function unpad($data) {
        $length = mcrypt_get_block_size(self::$cipher, self::$mode);
        $last = ord($data[strlen($data) - 1]);
        if ($last > $length || substr($data, -1 * $last) !== str_repeat(chr($last), $last)) {
            return $data;
        }
        return substr($data, 0, -1 * $last);
    }

    /**
     * key derivation wth pbkdf2: http://en.wikipedia.org/wiki/PBKDF2
     * @param string $algo hash algorithm to use
     * @param string $key payload
     * @param string $salt random string from generate_salt
     * @param string $count iterations
     * @param string $length result length
     */
    public static function pbkdf2($algo, $key, $salt, $count, $length) {
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
     * Return a unique-enough-key for session cookie ids
     * @param int $size length of the result
     * @return string
     */
    public static function unique_id($size=128) {
        return base64_encode(openssl_random_pseudo_bytes($size));
    }
}

?>
